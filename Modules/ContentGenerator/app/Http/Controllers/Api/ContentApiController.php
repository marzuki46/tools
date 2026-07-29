<?php

namespace Modules\ContentGenerator\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Jobs\ProcessContentGenerationJob;

class ContentApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'tone' => 'nullable|string|max:50',
            'lsi_keywords' => 'nullable|array|max:50',
            'lsi_keywords.*' => 'nullable',
            'entities' => 'nullable|array|max:30',
            'entities.*' => 'nullable',
        ]);

        $generation = ContentGeneration::create([
            'user_id' => $request->user()->id,
            'target_keyword' => $validated['keyword'],
            'locale' => $validated['locale'] ?? 'id',
            'tone' => $validated['tone'] ?? 'informative',
            'lsi_keywords' => $validated['lsi_keywords'] ?? [],
            'entities' => $validated['entities'] ?? [],
            'status' => 'draft',
            'current_phase' => 0,
        ]);

        if (config('queue.default') === 'sync' || app()->environment('local')) {
            dispatch_sync(new ProcessContentGenerationJob($generation));
        } else {
            ProcessContentGenerationJob::dispatch($generation);
        }

        return response()->json([
            'success' => true,
            'message' => 'Content generation queued successfully.',
            'data' => [
                'id' => $generation->id,
                'target_keyword' => $generation->target_keyword,
                'status' => $generation->fresh()->status,
            ],
        ], 202);
    }

    public function show($id): JsonResponse
    {
        $generation = ContentGeneration::where('user_id', request()->user()->id)
            ->findOrFail($id);

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
                'created_at' => $generation->created_at,
                'updated_at' => $generation->updated_at,
            ],
        ]);
    }
}
