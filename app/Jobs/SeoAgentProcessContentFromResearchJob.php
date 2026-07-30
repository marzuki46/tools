<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Services\ContentGeneratorService;
use Modules\KeywordResearch\Models\KeywordResearch;

class SeoAgentProcessContentFromResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected KeywordResearch $research,
        protected string $chatId,
        protected int $logId,
    ) {}

    public function handle(ContentGeneratorService $service): void
    {
        try {
            $generation = ContentGeneration::create([
                'user_id' => Setting::getValue('seo-agent.default_user_id', 1),
                'target_keyword' => $this->research->target_keyword,
                'locale' => $this->research->locale ?? 'id',
                'tone' => 'informative',
                'lsi_keywords' => $this->research->lsi_keywords ?? [],
                'entities' => $this->research->entities ?? [],
                'keyword_research_id' => $this->research->id,
                'status' => 'draft',
                'current_phase' => 0,
            ]);

            dispatch(new SeoAgentProcessContentJob($generation, $this->chatId, $this->logId));
        } catch (\Exception $e) {
            $telegram = app(TelegramService::class);
            $telegram->send(
                $this->chatId,
                "❌ Gagal membuat konten dari riset '{$this->research->target_keyword}': {$e->getMessage()}"
            );
        }
    }
}
