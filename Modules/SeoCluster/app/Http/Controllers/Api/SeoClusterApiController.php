<?php

namespace Modules\SeoCluster\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SeoCluster\Models\KeywordCluster;
use Modules\SeoCluster\Services\ClusterService;

class SeoClusterApiController extends Controller
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function index(): JsonResponse
    {
        $clusters = KeywordCluster::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'parent_keyword' => $c->parent_keyword,
                'status' => $c->status,
                'schedule' => $c->schedule,
                'progress' => $c->progress(),
                'created_at' => $c->created_at,
            ]);

        return response()->json(['data' => $clusters]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_keyword' => 'required|string|max:255',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'string|max:255',
            'description' => 'nullable|string|max:1000',
            'schedule' => 'nullable|string|in:manual,daily,every_6h,every_12h',
        ]);

        $cluster = $this->clusterService->createCluster(
            userId: auth()->id(),
            name: $validated['name'],
            parentKeyword: $validated['parent_keyword'],
            keywords: $validated['keywords'],
            description: $validated['description'] ?? null,
            schedule: $validated['schedule'] ?? 'manual',
        );

        return response()->json(['data' => $cluster->fresh()->load('keywords')], 201);
    }

    public function show($id): JsonResponse
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())
            ->with('keywords')
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $cluster->id,
                'name' => $cluster->name,
                'parent_keyword' => $cluster->parent_keyword,
                'description' => $cluster->description,
                'status' => $cluster->status,
                'schedule' => $cluster->schedule,
                'progress' => $cluster->progress(),
                'keywords' => $cluster->keywords,
                'image_keyword' => $cluster->image_keyword,
                'image_source' => $cluster->image_source,
                'image_enabled' => $cluster->image_enabled,
                'image_per_article' => $cluster->image_per_article,
                'created_at' => $cluster->created_at,
            ],
        ]);
    }

    public function progress($id): JsonResponse
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);

        return response()->json([
            'progress' => $cluster->progress(),
        ]);
    }

    public function activate($id): JsonResponse
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);
        $this->clusterService->activateCluster($cluster->id);

        return response()->json(['success' => true]);
    }

    public function pause($id): JsonResponse
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);
        $this->clusterService->pauseCluster($cluster->id);

        return response()->json(['success' => true]);
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => $this->clusterService->getAutomationSummary(),
        ]);
    }
}
