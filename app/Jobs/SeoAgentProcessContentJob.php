<?php

namespace App\Jobs;

use App\Models\SeoAgentLog;
use App\Services\FonnteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Services\ContentGeneratorService;

class SeoAgentProcessContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ContentGeneration $generation,
        protected string $sender,
        protected int $logId,
    ) {}

    public function handle(ContentGeneratorService $service): void
    {
        try {
            // Phase 1
            $this->generation->update(['current_phase' => 1, 'status' => 'processing']);
            $phase1 = $service->generatePhase1(
                $this->generation->target_keyword,
                $this->generation->locale ?? 'id',
                $this->generation->tone ?? 'informative',
                $this->generation->lsi_keywords ?? [],
                $this->generation->entities ?? [],
                null,
                null
            );
            $this->generation->update(['phase_1_content' => $phase1, 'current_phase' => 2]);

            // Phase 2
            $questions = $service->generatePhase2($phase1, $this->generation->target_keyword);
            $this->generation->update(['phase_2_questions' => $questions, 'current_phase' => 3]);

            // Phase 3
            $phase3 = $service->generatePhase3(
                $phase1,
                $questions,
                $this->generation->target_keyword,
                $this->generation->locale ?? 'id',
                $this->generation->tone ?? 'informative',
                $this->generation->lsi_keywords ?? [],
                $this->generation->entities ?? []
            );
            $this->generation->update([
                'phase_3_content' => $phase3,
                'status' => 'completed',
                'current_phase' => 3,
                'tokens_in' => $service->tokenUsage['tokens_in'] ?? 0,
                'tokens_out' => $service->tokenUsage['tokens_out'] ?? 0,
                'tokens_total' => ($service->tokenUsage['tokens_in'] ?? 0) + ($service->tokenUsage['tokens_out'] ?? 0),
            ]);

            // Generate meta
            try {
                $meta = $service->generateMetaData($phase3, $this->generation->target_keyword, $this->generation->locale ?? 'id');
                $this->generation->update([
                    'meta_title' => $meta['title'],
                    'meta_description' => $meta['description'],
                ]);
            } catch (\Exception $e) {
                // Non-critical
            }

            $fonnte = app(FonnteService::class);

            $wordCount = str_word_count(strip_tags($phase3));
            $preview = mb_substr(strip_tags($phase3), 0, 250);

            $reply = "✅ *KONTEN SELESAI*\n\n"
                . "Keyword: {$this->generation->target_keyword}\n"
                . "ID: {$this->generation->id}\n"
                . "Panjang: {$wordCount} kata\n\n"
                . "📝 *Preview:*\n{$preview}...\n\n"
                . "Perintah selanjutnya:\n"
                . "• `panjang {$this->generation->id}` — detail panjang\n"
                . "• `readability {$this->generation->id}` — cek readability\n"
                . "• `publish {$this->generation->id}` — publish ke WordPress";

            $fonnte->send($this->sender, $reply);

            SeoAgentLog::where('id', $this->logId)->update([
                'content_generation_id' => $this->generation->id,
            ]);
        } catch (\Exception $e) {
            $this->generation->update(['status' => 'failed']);

            $fonnte = app(FonnteService::class);
            $fonnte->send(
                $this->sender,
                "❌ Pembuatan konten '{$this->generation->target_keyword}' gagal di Phase {$this->generation->current_phase}: {$e->getMessage()}"
            );

            SeoAgentLog::where('id', $this->logId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'content_generation_id' => $this->generation->id,
            ]);
        }
    }
}
