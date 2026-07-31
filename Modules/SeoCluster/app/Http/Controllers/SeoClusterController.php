<?php

namespace Modules\SeoCluster\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\SeoCluster\Models\KeywordCluster;
use Modules\SeoCluster\Models\ClusterKeyword;
use Modules\SeoCluster\Services\ClusterService;

class SeoClusterController extends Controller
{
    public function __construct(
        private readonly ClusterService $clusterService
    ) {}

    public function index()
    {
        $clusters = KeywordCluster::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $stats = [
            'total' => KeywordCluster::where('user_id', auth()->id())->count(),
            'active' => KeywordCluster::where('user_id', auth()->id())->where('status', 'active')->count(),
            'total_published' => ClusterKeyword::whereIn('cluster_id', function ($q) {
                $q->select('id')->from('keyword_clusters')->where('user_id', auth()->id());
            })->where('status', 'published')->count(),
            'queue_pending' => DB::table('jobs')->count(),
            'queue_failed' => DB::table('failed_jobs')->count(),
        ];

        return view('seocluster::index', compact('clusters', 'stats'));
    }

    public function create()
    {
        return view('seocluster::create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_keyword' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'keywords' => 'required|string',
            'keywords.*' => 'string|max:255',
            'schedule' => 'nullable|string|in:manual,daily,every_6h,every_12h',
            'image_keyword' => 'nullable|string|max:255',
            'image_source' => 'nullable|string|in:duckduckgo,bing,unsplash',
            'image_per_article' => 'nullable|integer|min:0|max:10',
            'webp_quality' => 'nullable|integer|min:10|max:100',
        ]);

        $keywordLines = explode("\n", trim($validated['keywords']));
        $keywords = array_values(array_filter(array_map('trim', $keywordLines)));

        $cluster = $this->clusterService->createCluster(
            userId: auth()->id(),
            name: $validated['name'],
            parentKeyword: $validated['parent_keyword'],
            keywords: $keywords,
            description: $validated['description'] ?? null,
            schedule: $validated['schedule'] ?? 'manual',
            imageKeyword: $validated['image_keyword'] ?? null,
            imageSource: $validated['image_source'] ?? 'duckduckgo',
            imagePerArticle: (int) ($validated['image_per_article'] ?? 3),
            webpQuality: (int) ($validated['webp_quality'] ?? 80),
        );

        return redirect()->route('seocluster.show', $cluster->id)
            ->with('success', 'Cluster berhasil dibuat dengan ' . count($keywords) . ' keyword.');
    }

    public function show($id)
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);
        $keywords = $cluster->keywords()->orderBy('priority')->orderBy('id')->paginate(20);
        $progress = $cluster->progress();
        $logs = $cluster->automationLogs()->orderBy('created_at', 'desc')->take(10)->get();
        $analytics = $cluster->analytics()->orderBy('date', 'desc')->take(30)->get();

        return view('seocluster::show', compact('cluster', 'keywords', 'progress', 'logs', 'analytics'));
    }

    public function edit($id)
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);
        return view('seocluster::edit', compact('cluster'));
    }

    public function update(Request $request, $id)
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_keyword' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'schedule' => 'nullable|string|in:manual,daily,every_6h,every_12h',
            'image_keyword' => 'nullable|string|max:255',
            'image_source' => 'nullable|string|in:duckduckgo,bing,unsplash',
            'image_enabled' => 'nullable|boolean',
            'image_per_article' => 'nullable|integer|min:0|max:10',
            'webp_quality' => 'nullable|integer|min:10|max:100',
        ]);

        $cluster->update($validated);

        return redirect()->route('seocluster.show', $cluster->id)
            ->with('success', 'Cluster berhasil diupdate.');
    }

    public function destroy($id)
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);
        $cluster->delete();

        return redirect()->route('seocluster.index')
            ->with('success', 'Cluster berhasil dihapus.');
    }

    public function activate($id): JsonResponse
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);
        $this->clusterService->activateCluster($cluster->id);

        return response()->json(['success' => true, 'message' => 'Cluster diaktifkan.']);
    }

    public function pause($id): JsonResponse
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);
        $this->clusterService->pauseCluster($cluster->id);

        return response()->json(['success' => true, 'message' => 'Cluster dijeda.']);
    }

    public function addKeyword(Request $request, $id): JsonResponse
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);

        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'priority' => 'nullable|integer|min:0',
        ]);

        $keyword = $this->clusterService->addKeyword(
            clusterId: $cluster->id,
            keyword: $validated['keyword'],
            priority: (int) ($validated['priority'] ?? 0),
        );

        return response()->json(['success' => true, 'keyword' => $keyword]);
    }

    public function removeKeyword($id, $keywordId): JsonResponse
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);
        $deleted = $this->clusterService->removeKeyword((int) $keywordId);

        return response()->json(['success' => $deleted]);
    }

    public function progress($id): JsonResponse
    {
        $cluster = KeywordCluster::where('user_id', auth()->id())->findOrFail($id);

        return response()->json([
            'progress' => $cluster->progress(),
            'latest_logs' => $cluster->automationLogs()->orderBy('created_at', 'desc')->take(5)->get(),
        ]);
    }
}
