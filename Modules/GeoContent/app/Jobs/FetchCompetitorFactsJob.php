<?php

namespace Modules\GeoContent\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\GeoContent\Models\GeoProject;
use Modules\GeoContent\Services\CompetitorFactService;
use Modules\KeywordResearch\Services\KeywordResearchService;

class FetchCompetitorFactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public int $projectId) 
    {
        $this->onQueue(config('geo-content.queue', 'default'));
    }

    public function handle(CompetitorFactService $factService, KeywordResearchService $researchService): void
    {
        $project = GeoProject::find($this->projectId);
        if (!$project) return;

        try {
            $project->update(['status' => 'researching']);

            // Fase 1: riset keyword jika belum ada
            if (!$project->keyword_research_id) {
                $result = $researchService->research($project->keyword_utama, $project->locale ?? 'id');
                $research = \Modules\KeywordResearch\Models\KeywordResearch::create([
                    'user_id' => $project->user_id,
                    'target_keyword' => $project->keyword_utama,
                    'locale' => $project->locale ?? 'id',
                    'lsi_count' => count($result['lsi_keywords'] ?? []),
                    'entities_count' => count($result['entities'] ?? []),
                    'lsi_keywords' => $result['lsi_keywords'] ?? [],
                    'entities' => $result['entities'] ?? [],
                    'status' => 'completed',
                    'source' => 'geo-content',
                ]);
                $project->update(['keyword_research_id' => $research->id]);
            }

            $project->update(['status' => 'facts_fetching']);
            $factService->fetchForProject($project);
            $project->update(['status' => 'facts_ready']);

            // Auto lanjut ke pertanyaan kritis
            GenerateCriticalQuestionsJob::dispatch($project->id);
        } catch (\Throwable $e) {
            Log::error('GeoContent: FetchCompetitorFactsJob gagal', ['project' => $this->projectId, 'error' => $e->getMessage()]);
            $project->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        }
    }
}
