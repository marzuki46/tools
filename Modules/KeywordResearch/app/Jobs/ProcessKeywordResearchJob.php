<?php

namespace Modules\KeywordResearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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

            $this->research->update([
                'lsi_keywords' => $result['lsi_keywords'],
                'entities' => $result['entities'],
                'raw_response' => $result['raw_response'],
                'status' => 'completed',
            ]);

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
            ]);

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
}
