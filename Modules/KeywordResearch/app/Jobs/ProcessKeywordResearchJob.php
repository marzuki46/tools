<?php

namespace Modules\KeywordResearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\ApiKeyWebsite;
use Modules\KeywordResearch\Models\KeywordResearch;
use Modules\KeywordResearch\Services\KeywordResearchService;

class ProcessKeywordResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public KeywordResearch $research
    ) {
        $this->onQueue(config('keyword-research.queue', 'default'));
    }

    public function handle(KeywordResearchService $service): void
    {
        try {
            $this->research->update(['status' => 'processing']);

            $result = $service->research(
                $this->research->target_keyword,
                $this->research->locale,
                $this->research->lsi_count ?? 12,
                $this->research->entities_count ?? 7
            );

            $tokensIn = $service->tokenUsage['tokens_in'];
            $tokensOut = $service->tokenUsage['tokens_out'];

            $this->research->update([
                'lsi_keywords' => $result['lsi_keywords'],
                'entities' => $result['entities'],
                'raw_response' => $result['raw_response'],
                'status' => 'completed',
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'tokens_total' => $tokensIn + $tokensOut,
            ]);

            $this->syncTokenUsage();

            if ($this->research->webhook_url) {
                $ok = $service->sendWebhook(
                    $this->research->webhook_url,
                    $this->research->webhook_secret,
                    [
                        'event' => 'keyword.research.completed',
                        'id' => $this->research->id,
                        'target_keyword' => $this->research->target_keyword,
                        'lsi_keywords' => $result['lsi_keywords'],
                        'entities' => $result['entities'],
                        'status' => 'completed',
                    ]
                );

                if ($ok) {
                    $this->research->update(['webhook_sent_at' => now()]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Keyword Research Failed', [
                'id' => $this->research->id,
                'keyword' => $this->research->target_keyword,
                'error' => $e->getMessage(),
            ]);

            $this->research->update([
                'status' => 'failed',
                'raw_response' => ['error' => $e->getMessage()],
                'tokens_in' => $service->tokenUsage['tokens_in'],
                'tokens_out' => $service->tokenUsage['tokens_out'],
                'tokens_total' => $service->tokenUsage['tokens_in'] + $service->tokenUsage['tokens_out'],
            ]);

            $this->syncTokenUsage();

            if ($this->research->webhook_url) {
                $service = app(KeywordResearchService::class);
                $service->sendWebhook(
                    $this->research->webhook_url,
                    $this->research->webhook_secret,
                    [
                        'event' => 'keyword.research.failed',
                        'id' => $this->research->id,
                        'target_keyword' => $this->research->target_keyword,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }
    }

    private function syncTokenUsage(): void
    {
        if (!$this->research->api_key_website_id) {
            return;
        }

        try {
            $site = ApiKeyWebsite::find($this->research->api_key_website_id);
            if ($site) {
                $site->increment('tokens_in', $this->research->tokens_in);
                $site->increment('tokens_out', $this->research->tokens_out);
                $site->increment('tokens_total', $this->research->tokens_total);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync token usage to website', [
                'api_key_website_id' => $this->research->api_key_website_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
