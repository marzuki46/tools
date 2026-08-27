<?php

namespace Modules\GeoContent\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\GeoContent\Models\GeoProject;
use Modules\GeoContent\Services\CriticalQuestionService;

class GenerateCriticalQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public int $projectId)
    {
        $this->onQueue(config('geo-content.queue', 'default'));
    }

    public function handle(CriticalQuestionService $service): void
    {
        $project = GeoProject::find($this->projectId);
        if (!$project) return;

        try {
            $project->update(['status' => 'questions_generating']);
            $service->generate($project);
            $project->update(['status' => 'questions_ready']);
        } catch (\Throwable $e) {
            Log::error('GeoContent: GenerateCriticalQuestionsJob gagal', ['project' => $this->projectId, 'error' => $e->getMessage()]);
            $project->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        }
    }
}
