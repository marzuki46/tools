<?php

namespace Modules\MetaAdsImageGenerator\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\MetaAdsImageGenerator\Models\AdProject;
use Modules\MetaAdsImageGenerator\Models\AdGeneration;
use Modules\MetaAdsImageGenerator\Services\PromptBuilderService;
use Modules\MetaAdsImageGenerator\Services\ModerationService;
use Modules\MetaAdsImageGenerator\Jobs\GenerateAdCreativeJob;

class GenerateController extends Controller
{
    public function store(
        Request $request, 
        PromptBuilderService $promptBuilder,
        ModerationService $moderation
    ): JsonResponse {
        $request->validate([
            'project_id' => 'required|exists:ad_projects,id',
            'input_form' => 'required|array',
            'input_form.product_name' => 'required|string',
            'ai_provider' => 'nullable|string',
        ]);

        $project = AdProject::findOrFail($request->project_id);

        // Simple auth check (assuming auth middleware is applied in routes)
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $inputForm = $request->input('input_form');
        $compiledPrompt = $promptBuilder->build($inputForm);

        if (!$moderation->checkContent($compiledPrompt)) {
            return response()->json(['error' => 'Content flagged by moderation'], 400);
        }

        $provider = $request->input('ai_provider', config('meta-ads-image-generator.default_provider'));

        $generation = AdGeneration::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'input_form' => $inputForm,
            'compiled_prompt' => $compiledPrompt,
            'ai_provider' => $provider,
            'ai_model' => config("meta-ads-image-generator.providers.{$provider}.model", 'default'),
            'status' => 'queued',
        ]);

        GenerateAdCreativeJob::dispatch($generation);

        return response()->json([
            'message' => 'Generation queued successfully',
            'generation_id' => $generation->id,
        ], 202);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $generation = AdGeneration::with('exports')->findOrFail($id);

        if ($generation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $generation
        ]);
    }
}