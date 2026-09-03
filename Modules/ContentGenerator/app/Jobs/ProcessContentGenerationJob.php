<?php

namespace Modules\ContentGenerator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\BusinessProfile;
use App\Models\Tools\Tool;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Services\ContentGeneratorService;
use Modules\ContentGenerator\Services\MemoryService;

class ProcessContentGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 999;

    // Batas tinggi ulang-coba utk error sementara AI. Karena retry dilakukan
    // dengan dispatch job baru (attempts fresh), batas ini ~ jumlah hari item
    // boleh bertahan menunggu sebelum akhirnya ditandai failed. Prinsip: batch
    // tidak boleh "failed semua" karena error provider sesaat; item menunggu &
    // lanjut otomatis sampai berhasil (atau batas tinggi tercapai).
    public int $maxRetryCount = 500;

    public int $timeout = 1800;

    public function __construct(
        public ContentGeneration $generation,
        public int $targetPhase = 3
    ) {
        $this->onQueue(config('content-generator.queue', 'default'));
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function retryUntil(): \DateTime
    {
        // Jendela retry panjang agar item bertahan saat AI error sementara
        // / rate-limit berjam-jam, tanpa langsung gagal permanen.
        return now()->addHours(6);
    }

    public function handle(ContentGeneratorService $service): void
    {
        try {
            $lsiKeywords = $this->generation->lsi_keywords ?? [];
            $entities = $this->generation->entities ?? [];
            $linkSources = $this->generation->link_sources ?? [];
            $brief = null;

            if ($this->generation->content_brief_id) {
                $brief = \Modules\ContentGenerator\Models\ContentBrief::find($this->generation->content_brief_id);
                $brief = $brief ? $brief->toArray() : null;
            }

            // Phase 1
            if ($this->targetPhase >= 1 && empty($this->generation->phase_1_content)) {
                $this->generation->update(['status' => 'phase_1', 'current_phase' => 1]);

                $businessProfile = null;
                if ($this->generation->business_profile_id) {
                    $businessProfile = BusinessProfile::find($this->generation->business_profile_id);
                }

                $content = $service->generatePhase1(
                    $this->generation->target_keyword,
                    $this->generation->locale,
                    $this->generation->tone,
                    $lsiKeywords,
                    $entities,
                    $this->generation->user_id,
                    $businessProfile,
                    $this->generation->target_words,
                    $brief,
                    $this->generation->include_external_links
                );

                $this->generation->update(['phase_1_content' => $content]);
            }

            // Phase 2
            if ($this->targetPhase >= 2 && empty($this->generation->phase_2_questions)) {
                $this->generation->update(['status' => 'phase_2', 'current_phase' => 2]);

                $questions = $service->generatePhase2(
                    $this->generation->phase_1_content,
                    $this->generation->target_keyword
                );

                $this->generation->update(['phase_2_questions' => $questions]);
            }

            // Phase 3
            if ($this->targetPhase >= 3 && empty($this->generation->phase_3_content)) {
                $this->generation->update(['status' => 'phase_3', 'current_phase' => 3]);

                $businessProfile = null;
                if ($this->generation->business_profile_id) {
                    $businessProfile = BusinessProfile::find($this->generation->business_profile_id);
                }

                $website = null;
                if ($this->generation->api_key_website_id) {
                    $website = \App\Models\ApiKeyWebsite::find($this->generation->api_key_website_id);
                }

                $finalContent = $service->generatePhase3(
                    $this->generation->phase_1_content,
                    $this->generation->phase_2_questions ?? [],
                    $this->generation->target_keyword,
                    $this->generation->locale ?? 'id',
                    $this->generation->tone ?? 'informative',
                    $this->generation->lsi_keywords ?? [],
                    $this->generation->entities ?? [],
                    $this->generation->target_words,
                    $brief,
                    $linkSources,
                    $businessProfile,
                    $this->generation->include_external_links,
                    $website,
                    $this->generation->content_type ?? 'post'
                );

                if (trim((string) $finalContent) === '') {
                    throw new \RuntimeException('Phase 3 returned empty content');
                }

                $this->generation->update(['phase_3_content' => $finalContent]);
            }

            // Phase 4: Meta Data
            if ($this->targetPhase >= 3 && empty($this->generation->meta_title)) {
                $metaTool = Tool::where('slug', 'meta-generator')->first();
                if (!$metaTool || !$metaTool->is_active) {
                    Log::info('Meta generation skipped: tool is disabled');
                } else {
                try {
                    $meta = $service->generateMetaData(
                        $this->generation->phase_3_content,
                        $this->generation->target_keyword,
                        $this->generation->locale ?? 'id'
                    );
                        $this->generation->update([
                            'meta_title' => $meta['title'],
                            'meta_description' => $meta['description'],
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Meta generation failed', [
                            'id' => $this->generation->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Phase 5: Auto-generate Schema Markup (Article)
            try {
                $schemaTool = Tool::where('slug', 'schema-markup')->first();
                if ($schemaTool && $schemaTool->is_active && !\App\Models\SchemaMarkup::where('sourceable_type', get_class($this->generation))->where('sourceable_id', $this->generation->id)->exists()) {
                    $businessProfile = null;
                    $targetUrl = null;

                    if ($this->generation->business_profile_id) {
                        $businessProfile = BusinessProfile::find($this->generation->business_profile_id);
                        if ($businessProfile && $businessProfile->website_url) {
                            $targetUrl = $businessProfile->website_url;
                        }
                    }

                    if (!$targetUrl) {
                        $website = \App\Models\Websites\Website::where('user_id', $this->generation->user_id)->first();
                        if ($website && $website->domain) {
                            $targetUrl = str_starts_with($website->domain, 'http') ? $website->domain : 'https://' . $website->domain;
                        }
                    }

                    $schemaService = app(\App\Services\SchemaGeneratorService::class);
                    $autoData = $schemaService->autoFillFromContent('Article', $this->generation, $businessProfile);
                    $autoData['target_url'] = $targetUrl;

                    $generated = $schemaService->generate('Article', $autoData, $targetUrl, $this->generation, true);

                    \App\Models\SchemaMarkup::create([
                        'user_id' => $this->generation->user_id,
                        'name' => 'Auto: ' . ($this->generation->meta_title ?: $this->generation->target_keyword),
                        'schema_type' => 'Article',
                        'target_url' => $targetUrl,
                        'sourceable_type' => get_class($this->generation),
                        'sourceable_id' => $this->generation->id,
                        'data' => $autoData,
                        'generated' => $generated,
                        'use_ai' => true,
                    ]);

                    Log::info('Schema markup auto-generated', ['id' => $this->generation->id, 'url' => $targetUrl]);
                }
            } catch (\Exception $e) {
                Log::warning('Schema auto-generation failed', [
                    'id' => $this->generation->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $tokensIn = $service->tokenUsage['tokens_in'];
            $tokensOut = $service->tokenUsage['tokens_out'];
            $this->generation->update([
                'status' => 'completed',
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'tokens_total' => $tokensIn + $tokensOut,
            ]);

            $this->syncTokenUsage();

            Cache::put('queue_heartbeat', now()->toIso8601String(), 300);

            // Reset penghitung retry setelah sukses, agar item berikutnya mulai bersih.
            if ((int) $this->generation->retry_count !== 0) {
                $this->generation->update(['retry_count' => 0]);
            }

            try {
                app(MemoryService::class)->storeFromGeneration($this->generation);
            } catch (\Exception $e) {
                Log::warning('Failed to store generation memory', [
                    'id' => $this->generation->id,
                    'error' => $e->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'QUOTA_PAUSE')) {
                $at = now()->format('Y-m-d H:i:s');
                Log::warning('Content Generation quota/limit AI habis — jeda & lanjutkan otomatis', [
                    'id' => $this->generation->id,
                    'keyword' => $this->generation->target_keyword,
                    'paused_at' => $at,
                ]);
                $this->generation->update([
                    'status' => 'pending',
                    'raw_response' => [
                        'error' => 'Quota/limit AI tercapai. Akan dilanjutkan otomatis saat token pulih.',
                        'paused_at' => $at,
                    ],
                ]);

                // Jangan release() (berkonflik dengan retryUntil & menghabiskan attempts).
                // Dispatch job BARU yang ditunda 1 jam — attempts fresh, bertahan sampai token pulih.
                self::dispatch($this->generation->refresh(), $this->targetPhase)
                    ->delay(3600);

                return;
            }

            // Error sementara AI (429/5xx/koneksi/transient) — jangan gagal permanen.
            // Set pending & dispatch job baru yang ditunda, sampai berhasil atau
            // melewati batas tinggi retry. Bukan gagal massal seperti sebelumnya.
            if (str_contains($e->getMessage(), 'RETRYABLE')) {
                $retries = (int) ($this->generation->retry_count ?? 0);
                $retries++;
                $next = $this->retryDelaySeconds($retries);

                Log::warning('Content Generation AI sementara — jadwalkan ulang', [
                    'id' => $this->generation->id,
                    'keyword' => $this->generation->target_keyword,
                    'phase' => $this->targetPhase,
                    'retry' => $retries,
                    'next_in_seconds' => $next,
                    'error' => $e->getMessage(),
                ]);

                if ($retries > $this->maxRetryCount) {
                    $this->generation->update([
                        'status' => 'failed',
                        'raw_response' => ['error' => 'AI sibuk/error sementara terlalu lama setelah ' . $retries . ' percobaan. ' . $e->getMessage()],
                    ]);
                    $this->syncTokenUsage();
                    $this->fail($e);
                    return;
                }

                $this->generation->update([
                    'retry_count' => $retries,
                    'status' => 'pending',
                    'raw_response' => [
                        'error' => 'AI sementara sibuk — akan dicoba lagi otomatis.',
                        'next_retry_at' => now()->addSeconds($next)->toDateTimeString(),
                    ],
                ]);

                // Bukan release() (berkonflik dengan retryUntil & menghabiskan attempts),
                // dispatch job BARU yang ditunda — attempts fresh, bertahan lama.
                self::dispatch($this->generation->refresh(), $this->targetPhase)
                    ->delay($next);

                return;
            }

            Log::error('Content Generation Failed', [
                'id' => $this->generation->id,
                'keyword' => $this->generation->target_keyword,
                'phase' => $this->targetPhase,
                'error' => $e->getMessage(),
            ]);

            $this->generation->update([
                'status' => 'failed',
                'raw_response' => ['error' => $e->getMessage()],
                'tokens_in' => $service->tokenUsage['tokens_in'],
                'tokens_out' => $service->tokenUsage['tokens_out'],
                'tokens_total' => $service->tokenUsage['tokens_in'] + $service->tokenUsage['tokens_out'],
            ]);

            $this->syncTokenUsage();

            $this->fail($e);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Content Generation permanently failed after retries', [
            'id' => $this->generation->id,
            'keyword' => $this->generation->target_keyword,
            'error' => $e->getMessage(),
        ]);

        $this->generation->update([
            'status' => 'failed',
            'raw_response' => ['error' => 'Gagal diproses: ' . $e->getMessage()],
        ]);
    }

    private function syncTokenUsage(): void
    {
        if (!$this->generation->api_key_website_id) {
            return;
        }

        try {
            $site = \App\Models\ApiKeyWebsite::find($this->generation->api_key_website_id);
            if ($site) {
                $site->increment('tokens_in', $this->generation->tokens_in);
                $site->increment('tokens_out', $this->generation->tokens_out);
                $site->increment('tokens_total', $this->generation->tokens_total);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync token usage to website', [
                'api_key_website_id' => $this->generation->api_key_website_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Backoff eksponensial utk retry: 2m, 4m, 8m, ... cap 30 menit.
    // Ulang-coba jarang-jarang awal, lalu makin lebar kalau provider masih sibuk —
    // tanpa menyerbu provider dan tanpa "failed semua".
    private function retryDelaySeconds(int $retry): int
    {
        $seconds = min(1800, 120 * (2 ** ($retry - 1)));
        return (int) $seconds;
    }
}
