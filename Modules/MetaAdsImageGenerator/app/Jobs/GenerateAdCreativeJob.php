<?php

namespace Modules\MetaAdsImageGenerator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\MetaAdsImageGenerator\Models\AdGeneration;
use Modules\MetaAdsImageGenerator\Models\AiUsageLog;
use Modules\MetaAdsImageGenerator\Services\AiProviderManager;
use Modules\MetaAdsImageGenerator\Services\MultiSizeRendererService;
use Exception;

class GenerateAdCreativeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public AdGeneration $generation
    ) {}

    public function handle(AiProviderManager $aiManager, MultiSizeRendererService $renderer): void
    {
        try {
            $this->generation->update(['status' => 'processing']);

            // 1. Generate Base Image via AI
            $aiResult = $aiManager->generateImage(
                $this->generation->compiled_prompt,
                $this->generation->ai_provider
            );

            $baseImageUrl = $aiResult['url'];

            // 2. Download the AI image to local storage
            $localPath = $this->downloadImage($baseImageUrl, $this->generation->id);

            // 3. Update generation with local path & AI metadata
            $this->generation->update([
                'base_image_path' => $localPath,
                'ai_raw_response' => $aiResult['raw_response'],
                'ai_model' => $aiResult['model'],
            ]);

            // 4. Log AI Usage
            AiUsageLog::create([
                'user_id' => $this->generation->user_id,
                'generation_id' => $this->generation->id,
                'provider' => $this->generation->ai_provider,
                'tokens_or_units' => 1,
                'estimated_cost' => 0.040,
            ]);

            // 5. Render Multi-Sizes using local image
            $brandKit = $this->generation->project->brandKit
                ? $this->generation->project->brandKit->toArray()
                : [];

            $localAbsolutePath = Storage::disk('public')->path($localPath);

            $exports = $renderer->render(
                $localAbsolutePath,
                $brandKit,
                $this->generation->input_form,
                (string) $this->generation->id
            );

            foreach ($exports as $exportData) {
                $this->generation->exports()->create($exportData);
            }

            $this->generation->update(['status' => 'done']);

        } catch (Exception $e) {
            Log::error('Ad Generation Failed', [
                'id' => $this->generation->id,
                'error' => $e->getMessage(),
            ]);
            $this->generation->update(['status' => 'failed']);
        }
    }

    /**
     * Download an image from a URL and save it to the public storage disk.
     */
    private function downloadImage(string $url, int $generationId): string
    {
        $response = Http::timeout(60)->get($url);

        if (!$response->successful()) {
            throw new Exception("Failed to download AI image from: {$url}");
        }

        $filename = "meta-ads/{$generationId}/base.png";
        Storage::disk('public')->put($filename, $response->body());

        return $filename;
    }
}
