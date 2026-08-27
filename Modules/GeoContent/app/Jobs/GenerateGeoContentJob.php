<?php

namespace Modules\GeoContent\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\GeoContent\Models\GeoProject;
use Modules\GeoContent\Services\GeoContentService;

class GenerateGeoContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(public int $projectId)
    {
        $this->onQueue(config('geo-content.queue', 'default'));
    }

    public function handle(GeoContentService $service): void
    {
        $project = GeoProject::find($this->projectId);
        if (!$project) return;

        try {
            $project->update(['status' => 'generating']);
            $service->generateForProject($project);
            $project->update(['status' => 'review']);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'MONTHLY_REQUEST_COUNT')) {
                Log::warning('GeoContent: quota habis, tunda 1 jam', ['project' => $this->projectId]);
                $project->update(['status' => 'pending', 'error_message' => 'Quota AI habis — tunggu 1 jam / ganti model.']);
                $this->release(3600);
                return;
            }
            Log::error('GeoContent: GenerateGeoContentJob gagal', ['project' => $this->projectId, 'error' => $e->getMessage()]);
            $project->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        }
    }
}
