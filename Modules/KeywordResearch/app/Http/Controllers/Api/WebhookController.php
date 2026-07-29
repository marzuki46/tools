<?php

namespace Modules\KeywordResearch\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\KeywordResearch\Jobs\ProcessKeywordResearchJob;
use Modules\KeywordResearch\Models\KeywordResearch;

class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:255',
        ]);

        $research = KeywordResearch::create([
            'user_id' => auth()->id(),
            'target_keyword' => $validated['keyword'],
            'locale' => $validated['locale'] ?? 'id',
            'status' => 'pending',
            'source' => 'webhook',
            'webhook_url' => $validated['webhook_url'] ?? null,
            'webhook_secret' => $validated['webhook_secret'] ?? null,
        ]);

        ProcessKeywordResearchJob::dispatch($research);

        return response()->json([
            'success' => true,
            'message' => 'Keyword research queued successfully.',
            'data' => [
                'id' => $research->id,
                'target_keyword' => $research->target_keyword,
                'status' => $research->status,
            ],
        ], 202);
    }

    public function research(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'lsi_count' => 'nullable|integer|min:3|max:50',
            'entities_count' => 'nullable|integer|min:1|max:30',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:255',
        ]);

        $research = KeywordResearch::create([
            'user_id' => auth()->id(),
            'target_keyword' => $validated['keyword'],
            'locale' => $validated['locale'] ?? 'id',
            'lsi_count' => $validated['lsi_count'] ?? 12,
            'entities_count' => $validated['entities_count'] ?? 7,
            'status' => 'pending',
            'source' => 'api',
            'webhook_url' => $validated['webhook_url'] ?? null,
            'webhook_secret' => $validated['webhook_secret'] ?? null,
        ]);

        ProcessKeywordResearchJob::dispatch($research);

        return response()->json([
            'success' => true,
            'message' => 'Keyword research queued successfully.',
            'data' => [
                'id' => $research->id,
                'target_keyword' => $research->target_keyword,
                'lsi_count' => $research->lsi_count,
                'entities_count' => $research->entities_count,
                'status' => $research->status,
            ],
        ], 202);
    }

    public function status($id): JsonResponse
    {
        $research = KeywordResearch::where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $research->id,
                'target_keyword' => $research->target_keyword,
                'status' => $research->status,
                'lsi_keywords' => $research->lsi_keywords,
                'entities' => $research->entities,
                'created_at' => $research->created_at,
                'updated_at' => $research->updated_at,
            ],
        ]);
    }
}
