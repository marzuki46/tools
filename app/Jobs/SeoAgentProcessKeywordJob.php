<?php

namespace App\Jobs;

use App\Models\SeoAgentLog;
use App\Services\FonnteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\KeywordResearch\Models\KeywordResearch;
use Modules\KeywordResearch\Services\KeywordResearchService;

class SeoAgentProcessKeywordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected KeywordResearch $research,
        protected string $sender,
        protected int $logId,
        protected bool $autoGenerateContent = false,
    ) {}

    public function handle(KeywordResearchService $service): void
    {
        try {
            $this->research->update(['status' => 'processing']);

            $result = $service->research(
                $this->research->target_keyword,
                $this->research->locale ?? 'id',
                $this->research->lsi_count ?? 12,
                $this->research->entities_count ?? 7
            );

            $this->research->update([
                'lsi_keywords' => $result['lsi_keywords'] ?? [],
                'entities' => $result['entities'] ?? [],
                'raw_response' => $result['raw_response'] ?? null,
                'status' => 'completed',
                'tokens_in' => $service->tokenUsage['tokens_in'] ?? 0,
                'tokens_out' => $service->tokenUsage['tokens_out'] ?? 0,
                'tokens_total' => ($service->tokenUsage['tokens_in'] ?? 0) + ($service->tokenUsage['tokens_out'] ?? 0),
            ]);

            $fonnte = app(FonnteService::class);

            $lsiPreview = collect($result['lsi_keywords'] ?? [])
                ->take(10)
                ->pluck('keyword')
                ->implode("\n• ");

            $entityPreview = collect($result['entities'] ?? [])
                ->take(5)
                ->map(fn($e) => $e['name'] . ' (' . ($e['type'] ?? '') . ')')
                ->implode("\n• ");

            $reply = "✅ *RISET SELESAI*\n\n"
                . "Keyword: {$this->research->target_keyword}\n"
                . "ID: {$this->research->id}\n\n"
                . "📌 *LSI Keywords:*\n• {$lsiPreview}\n\n"
                . "🏷️ *Entities:*\n• {$entityPreview}\n\n"
                . "Gunakan `konten {$this->research->target_keyword}` untuk buat artikel.";

            $fonnte->send($this->sender, $reply);

            SeoAgentLog::where('id', $this->logId)->update([
                'keyword_research_id' => $this->research->id,
            ]);

            // Auto-generate content if requested
            if ($this->autoGenerateContent) {
                dispatch(new SeoAgentProcessContentFromResearchJob(
                    $this->research,
                    $this->sender,
                    $this->logId,
                ));
            }
        } catch (\Exception $e) {
            $this->research->update(['status' => 'failed']);

            $fonnte = app(FonnteService::class);
            $fonnte->send(
                $this->sender,
                "❌ Riset keyword '{$this->research->target_keyword}' gagal: {$e->getMessage()}"
            );

            SeoAgentLog::where('id', $this->logId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'keyword_research_id' => $this->research->id,
            ]);
        }
    }
}
