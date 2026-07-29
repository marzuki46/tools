<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tools\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\ContentGenerator\Jobs\ProcessContentGenerationJob;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Services\ContentGeneratorService;
use Modules\KeywordResearch\Jobs\ProcessKeywordResearchJob;
use Modules\KeywordResearch\Models\KeywordResearch;

class ToolApiController extends Controller
{
    private array $handlers = [
        'keyword-research' => [
            'research' => 'handleKeywordResearch',
            'status' => 'handleKeywordResearchStatus',
        ],
        'content-generator' => [
            'generate' => 'handleContentGenerate',
            'status' => 'handleContentStatus',
            'generate-meta' => 'handleGenerateMeta',
        ],
    ];

    public function execute(Request $request, string $tool, string $action): JsonResponse
    {
        $toolModel = Tool::where('slug', $tool)->where('is_active', true)->first();

        if (!$toolModel) {
            return response()->json(['success' => false, 'message' => 'Tool not found.'], 404);
        }

        if (!auth()->user()->hasToolAccess($tool)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this tool.'], 403);
        }

        $actions = $this->handlers[$tool] ?? null;

        if (!$actions || !isset($actions[$action])) {
            return response()->json(['success' => false, 'message' => "Action '{$action}' is not supported for '{$tool}'."], 400);
        }

        return $this->{$actions[$action]}($request);
    }

    private function handleKeywordResearch(Request $request): JsonResponse
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
                'status' => 'pending',
            ],
        ], 202);
    }

    private function handleKeywordResearchStatus(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $research = KeywordResearch::where('user_id', auth()->id())
            ->findOrFail($request->id);

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

    private function handleContentGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'tone' => 'nullable|string|max:50',
            'lsi_keywords' => 'nullable|array|max:50',
            'lsi_keywords.*' => 'nullable',
            'entities' => 'nullable|array|max:30',
            'entities.*' => 'nullable',
            'business_profile_id' => 'nullable|integer|exists:business_profiles,id',
            'keyword_research_id' => 'nullable|integer|exists:keyword_researches,id',
        ]);

        $generation = ContentGeneration::create([
            'user_id' => auth()->id(),
            'target_keyword' => $validated['keyword'],
            'locale' => $validated['locale'] ?? 'id',
            'tone' => $validated['tone'] ?? 'informative',
            'lsi_keywords' => $validated['lsi_keywords'] ?? [],
            'entities' => $validated['entities'] ?? [],
            'business_profile_id' => $validated['business_profile_id'] ?? null,
            'keyword_research_id' => $validated['keyword_research_id'] ?? null,
            'status' => 'draft',
            'current_phase' => 0,
        ]);

        ProcessContentGenerationJob::dispatch($generation);

        return response()->json([
            'success' => true,
            'message' => 'Content generation queued successfully.',
            'data' => [
                'id' => $generation->id,
                'target_keyword' => $generation->target_keyword,
                'status' => 'draft',
            ],
        ], 202);
    }

    private function handleContentStatus(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $generation = ContentGeneration::where('user_id', auth()->id())
            ->findOrFail($request->id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $generation->id,
                'target_keyword' => $generation->target_keyword,
                'status' => $generation->status,
                'current_phase' => $generation->current_phase,
                'phase_1_content' => $generation->phase_1_content,
                'phase_2_questions' => $generation->phase_2_questions,
                'phase_3_content' => $generation->phase_3_content,
                'meta_title' => $generation->meta_title,
                'meta_description' => $generation->meta_description,
                'created_at' => $generation->created_at,
                'updated_at' => $generation->updated_at,
            ],
        ]);
    }

    private function handleGenerateMeta(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $generation = ContentGeneration::where('user_id', auth()->id())
            ->findOrFail($request->id);

        if (empty($generation->phase_3_content)) {
            return response()->json(['success' => false, 'message' => 'Phase 3 must be completed first.'], 400);
        }

        try {
            $meta = app(ContentGeneratorService::class)->generateMetaData(
                $generation->phase_3_content,
                $generation->target_keyword,
                $generation->locale ?? 'id'
            );

            $generation->update([
                'meta_title' => $meta['title'],
                'meta_description' => $meta['description'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $generation->id,
                    'meta_title' => $meta['title'],
                    'meta_description' => $meta['description'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Generate meta API failed', [
                'id' => $generation->id, 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to generate meta.'], 500);
        }
    }
}
