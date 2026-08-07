<?php

namespace Modules\ContentGenerator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\ApiKeyWebsite;
use Modules\ContentGenerator\Models\ContentBrief;
use Modules\ContentGenerator\Services\ContentGeneratorService;

class ProcessContentBriefJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ContentBrief $brief
    ) {
        $this->onQueue(config('content-generator.queue', 'default'));
    }

    public function handle(ContentGeneratorService $service): void
    {
        try {
            $this->brief->update(['status' => 'processing']);

            $result = $service->buildBrief(
                $this->brief->target_keyword,
                $this->brief->locale,
                count($this->brief->keywords ?? []) ?: 10
            );

            $this->brief->update([
                'meta_title' => $result['meta_title'],
                'h1_tag' => $result['h1_tag'],
                'url_slug' => $result['url_slug'],
                'target_audience' => $result['target_audience'],
                'pain_point' => $result['pain_point'],
                'local_entities' => $result['local_entities'],
                'keywords' => $result['keywords'],
                'raw_response' => $result['raw_response'],
                'status' => 'completed',
                'error_message' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Content Brief Failed', [
                'id' => $this->brief->id,
                'keyword' => $this->brief->target_keyword,
                'error' => $e->getMessage(),
            ]);

            $this->brief->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
