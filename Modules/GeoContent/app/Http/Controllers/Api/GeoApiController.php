<?php

namespace Modules\GeoContent\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GeoContent\Models\GeoProject;
use Modules\GeoContent\Jobs\FetchCompetitorFactsJob;
use Modules\GeoContent\Jobs\GenerateCriticalQuestionsJob;
use Modules\GeoContent\Jobs\GenerateGeoContentJob;

class GeoApiController extends Controller
{
    public function index(Request $request)
    {
        $projects = GeoProject::where('user_id', $request->user()->id ?? auth()->id())->latest()->paginate(20);
        return response()->json(['data' => $projects]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'keyword_utama' => 'required|string|max:255',
            'competitor_urls' => 'required|array|min:1|max:5',
            'competitor_urls.*' => 'required|url',
            'mode' => 'required|in:baru,revisi',
            'wp_post_id' => 'nullable|integer',
        ]);

        $project = GeoProject::create([
            'user_id' => $request->user()->id ?? auth()->id(),
            'keyword_utama' => $validated['keyword_utama'],
            'competitor_urls' => $validated['competitor_urls'],
            'mode' => $validated['mode'],
            'wp_post_id' => $validated['wp_post_id'] ?? null,
            'status' => 'draft',
        ]);

        FetchCompetitorFactsJob::dispatch($project->id);

        return response()->json(['data' => $project], 201);
    }

    public function show(GeoProject $project)
    {
        $project->load(['sourceFacts', 'criticalFindings', 'contents', 'diff']);
        return response()->json(['data' => $project]);
    }

    public function fetchFacts(GeoProject $project)
    {
        FetchCompetitorFactsJob::dispatch($project->id);
        return response()->json(['message' => 'Fetch diantrikan']);
    }

    public function generateQuestions(GeoProject $project)
    {
        GenerateCriticalQuestionsJob::dispatch($project->id);
        return response()->json(['message' => 'Generate pertanyaan diantrikan']);
    }

    public function generate(GeoProject $project)
    {
        GenerateGeoContentJob::dispatch($project->id);
        return response()->json(['message' => 'Generate konten diantrikan']);
    }

    public function publish(GeoProject $project)
    {
        GenerateGeoContentJob::dispatch($project->id);
        return response()->json(['message' => 'Publish diantrikan']);
    }
}
