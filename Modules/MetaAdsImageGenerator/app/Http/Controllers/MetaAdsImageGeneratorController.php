<?php

namespace Modules\MetaAdsImageGenerator\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\MetaAdsImageGenerator\Jobs\GenerateAdCreativeJob;
use Modules\MetaAdsImageGenerator\Models\AdAsset;
use Modules\MetaAdsImageGenerator\Models\AdBrandKit;
use Modules\MetaAdsImageGenerator\Models\AdGeneration;
use Modules\MetaAdsImageGenerator\Models\AdPreset;
use Modules\MetaAdsImageGenerator\Models\AdProject;
use Modules\MetaAdsImageGenerator\Services\AiProviderManager;
use Modules\MetaAdsImageGenerator\Services\CopywritingService;
use Modules\MetaAdsImageGenerator\Services\ModerationService;
use Modules\MetaAdsImageGenerator\Services\MultiSizeRendererService;
use Modules\MetaAdsImageGenerator\Services\PromptBuilderService;

class MetaAdsImageGeneratorController extends Controller
{
    public function index()
    {
        $projects = AdProject::where('user_id', auth()->id())
            ->withCount('generations')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $recentGenerations = AdGeneration::where('user_id', auth()->id())
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        $stats = [
            'total_projects' => AdProject::where('user_id', auth()->id())->count(),
            'total_generations' => AdGeneration::where('user_id', auth()->id())->count(),
            'completed' => AdGeneration::where('user_id', auth()->id())->where('status', 'done')->count(),
        ];

        return view('metaadsimagegenerator::index', compact('projects', 'recentGenerations', 'stats'));
    }

    public function create()
    {
        $projects = AdProject::where('user_id', auth()->id())->orderBy('name')->get();
        $brandKits = AdBrandKit::where('user_id', auth()->id())->orderBy('is_default', 'desc')->get();
        $presets = AdPreset::where(function ($q) {
            $q->whereNull('user_id')->orWhere('user_id', auth()->id());
        })->where('is_active', true)->get();

        return view('metaadsimagegenerator::create', compact('projects', 'brandKits', 'presets'));
    }

    public function store(Request $request, PromptBuilderService $promptBuilder, ModerationService $moderation)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:ad_projects,id',
            'preset_id' => 'nullable|exists:ad_presets,id',
            'brand_kit_id' => 'nullable|exists:ad_brand_kits,id',
            'product_name' => 'required|string|max:255',
            'headline' => 'nullable|string|max:500',
            'sub_headline' => 'nullable|string|max:500',
            'cta' => 'nullable|string|max:200',
            'vibe' => 'nullable|string|max:255',
            'target_audience' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'sizes' => 'nullable|array',
            'sizes.*' => 'in:1:1,9:16,1.91:1',
            'ai_provider' => 'nullable|string|in:openai,stability,pollinations',
            'ai_model_override' => 'nullable|string|max:255',
            'product_image' => 'nullable|image|mimes:png,jpeg,webp|max:5120',
            'model_image' => 'nullable|image|mimes:png,jpeg,webp|max:5120',
        ]);

        $project = AdProject::where('user_id', auth()->id())->findOrFail($validated['project_id']);

        if ($request->filled('brand_kit_id')) {
            $kit = AdBrandKit::where('user_id', auth()->id())->find($validated['brand_kit_id']);
            if ($kit) {
                $project->update(['brand_kit_id' => $kit->id]);
            }
        }

        $assetId = null;
        if ($request->hasFile('product_image')) {
            $path = $this->storeResizedImage($request->file('product_image'), "ads/{$project->id}/product");
            $asset = AdAsset::create([
                'project_id' => $project->id,
                'file_path' => $path,
                'original_name' => $request->file('product_image')->getClientOriginalName(),
                'mime_type' => 'image/webp',
                'size_kb' => 0,
            ]);
            $assetId = $asset->id;
        }

        $modelAssetId = null;
        if ($request->hasFile('model_image')) {
            $path = $this->storeResizedImage($request->file('model_image'), "ads/{$project->id}/model");
            $modelAsset = AdAsset::create([
                'project_id' => $project->id,
                'file_path' => $path,
                'original_name' => $request->file('model_image')->getClientOriginalName(),
                'mime_type' => 'image/webp',
                'size_kb' => 0,
            ]);
            $modelAssetId = $modelAsset->id;
        }

        $provider = $validated['ai_provider'] ?? 'pollinations';
        $modelOverride = $validated['ai_model_override'] ?? null;
        $aiModel = $modelOverride ?: ($provider === 'pollinations' ? 'pollinations-flux' : config("meta-ads-image-generator.providers.{$provider}.model", 'default'));

        $inputForm = [
            'product_name' => $validated['product_name'],
            'headline' => $validated['headline'] ?? '',
            'sub_headline' => $validated['sub_headline'] ?? '',
            'cta' => $validated['cta'] ?? '',
            'vibe' => $validated['vibe'] ?? '',
            'target_audience' => $validated['target_audience'] ?? '',
            'notes' => $validated['notes'] ?? '',
            'sizes' => $validated['sizes'] ?? ['1:1', '9:16', '1.91:1'],
            'ai_provider' => $provider,
            'has_product_image' => $assetId !== null,
            'has_model_image' => $modelAssetId !== null,
        ];

        $presetTemplate = null;
        if ($validated['preset_id'] ?? null) {
            $preset = AdPreset::find($validated['preset_id']);
            $presetTemplate = $preset?->prompt_template;
        }

        $compiledPrompt = $promptBuilder->build($inputForm, $presetTemplate);

        $moderated = $moderation->checkContent($compiledPrompt);

        $generation = AdGeneration::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'preset_id' => $validated['preset_id'] ?? null,
            'asset_id' => $assetId,
            'model_asset_id' => $modelAssetId,
            'input_form' => $inputForm,
            'compiled_prompt' => $compiledPrompt,
            'ai_provider' => $provider,
            'ai_model' => $aiModel,
            'status' => 'processing',
            'credit_used' => config('meta-ads-image-generator.credits.per_generation', 1),
            'moderation_flag' => !$moderated,
        ]);

        try {
            $productName = $inputForm['product_name'] ?? 'product';
            $vibe = $inputForm['vibe'] ?? 'professional';
            $audience = $inputForm['target_audience'] ?? '';

            $styleMap = [
                'minimalis' => 'minimalist clean white background, soft shadows, simple composition',
                'bold-promo' => 'bold vibrant promotional style, dynamic angles, energetic colors',
                'elegant' => 'luxury elegant style, dark premium background, gold accents, sophisticated',
                'playful' => 'fun playful colorful style, bright cheerful atmosphere, creative',
                'professional' => 'professional corporate style, clean modern studio lighting',
                'luxury' => 'high-end luxury premium style, velvet background, dramatic lighting',
            ];
            $style = $styleMap[$vibe] ?? $styleMap['professional'];

            $aiPrompt = "Professional product photography advertisement for {$productName}. " .
                "{$style}. " .
                "High quality commercial photography, studio lighting, sharp focus, " .
                "photorealistic, 8k, award winning photography. " .
                "Leave space at bottom for text overlay. " .
                "No text or words in the image.";

            if (!empty($inputForm['notes'])) {
                $aiPrompt .= " " . $inputForm['notes'];
            }

            $aiResult = app(AiProviderManager::class)->generateImage($aiPrompt, 'pollinations');

            $baseImageUrl = $aiResult['url'];
            $localPath = "meta-ads/{$generation->id}/base.png";
            Storage::disk('public')->put($localPath, Http::timeout(120)->get($baseImageUrl)->body());

            $generation->update([
                'base_image_path' => $localPath,
                'ai_raw_response' => $aiResult['raw_response'],
                'ai_model' => $aiResult['model'],
            ]);

            $brandKit = $project->brandKit ? $project->brandKit->toArray() : [];
            $localAbsolutePath = Storage::disk('public')->path($localPath);
            $exports = app(MultiSizeRendererService::class)->renderFromAi(
                $localAbsolutePath,
                $brandKit,
                $inputForm,
                (string) $generation->id
            );

            foreach ($exports as $exportData) {
                $generation->exports()->create($exportData);
            }

            $generation->update(['status' => 'done']);
        } catch (\Exception $e) {
            Log::error('Ad Generation Failed', ['id' => $generation->id, 'error' => $e->getMessage()]);
            $generation->update(['status' => 'failed']);
        }

        return redirect()->route('metaadsimagegenerator.show', $generation->id)
            ->with('success', 'Generation processed.');
    }

    public function show($id)
    {
        $generation = AdGeneration::with(['project.brandKit', 'exports', 'asset', 'modelAsset'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return view('metaadsimagegenerator::show', compact('generation'));
    }

    public function edit($id)
    {
        $generation = AdGeneration::where('user_id', auth()->id())->findOrFail($id);
        $projects = AdProject::where('user_id', auth()->id())->orderBy('name')->get();
        $brandKits = AdBrandKit::where('user_id', auth()->id())->get();
        $presets = AdPreset::where(function ($q) {
            $q->whereNull('user_id')->orWhere('user_id', auth()->id());
        })->where('is_active', true)->get();

        return view('metaadsimagegenerator::edit', compact('generation', 'projects', 'brandKits', 'presets'));
    }

    public function update(Request $request, $id)
    {
        $generation = AdGeneration::where('user_id', auth()->id())->findOrFail($id);

        $validated = $request->validate([
            'project_id' => 'required|exists:ad_projects,id',
            'preset_id' => 'nullable|exists:ad_presets,id',
            'brand_kit_id' => 'nullable|exists:ad_brand_kits,id',
            'product_name' => 'required|string|max:255',
            'headline' => 'nullable|string|max:500',
            'cta' => 'nullable|string|max:200',
            'vibe' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($request->filled('brand_kit_id')) {
            $project = $generation->project;
            $kit = AdBrandKit::where('user_id', auth()->id())->find($validated['brand_kit_id']);
            if ($kit) {
                $project->update(['brand_kit_id' => $kit->id]);
            }
        }

        $inputForm = $generation->input_form;
        $inputForm['product_name'] = $validated['product_name'];
        $inputForm['headline'] = $validated['headline'] ?? '';
        $inputForm['cta'] = $validated['cta'] ?? '';
        $inputForm['vibe'] = $validated['vibe'] ?? '';
        $inputForm['notes'] = $validated['notes'] ?? '';

        $generation->update([
            'project_id' => $validated['project_id'],
            'preset_id' => $validated['preset_id'] ?? null,
            'input_form' => $inputForm,
        ]);

        return redirect()->route('metaadsimagegenerator.show', $generation->id)
            ->with('success', 'Generation updated.');
    }

    public function destroy($id)
    {
        $generation = AdGeneration::where('user_id', auth()->id())->findOrFail($id);
        $generation->delete();

        return redirect()->route('metaadsimagegenerator.index')
            ->with('success', 'Generation deleted.');
    }

    public function generateCopy(Request $request, CopywritingService $copywriting): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'headline_hint' => 'nullable|string|max:500',
            'vibe' => 'nullable|string|max:255',
            'target_audience' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $variations = $copywriting->generateCopy($validated);
            return response()->json(['success' => true, 'variations' => $variations]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function regenerate($id)
    {
        $generation = AdGeneration::with(['project.brandKit'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $generation->exports()->delete();
        $generation->update(['status' => 'processing']);

        try {
            $inputForm = $generation->input_form;
            $productName = $inputForm['product_name'] ?? 'product';
            $vibe = $inputForm['vibe'] ?? 'professional';

            $styleMap = [
                'minimalis' => 'minimalist clean white background, soft shadows, simple composition',
                'bold-promo' => 'bold vibrant promotional style, dynamic angles, energetic colors',
                'elegant' => 'luxury elegant style, dark premium background, gold accents, sophisticated',
                'playful' => 'fun playful colorful style, bright cheerful atmosphere, creative',
                'professional' => 'professional corporate style, clean modern studio lighting',
                'luxury' => 'high-end luxury premium style, velvet background, dramatic lighting',
            ];
            $style = $styleMap[$vibe] ?? $styleMap['professional'];

            $aiPrompt = "Professional product photography advertisement for {$productName}. " .
                "{$style}. " .
                "High quality commercial photography, studio lighting, sharp focus, " .
                "photorealistic, 8k, award winning photography. " .
                "Leave space at bottom for text overlay. " .
                "No text or words in the image.";

            if (!empty($inputForm['notes'])) {
                $aiPrompt .= " " . $inputForm['notes'];
            }

            $aiResult = app(AiProviderManager::class)->generateImage($aiPrompt, 'pollinations');

            $baseImageUrl = $aiResult['url'];
            $localPath = "meta-ads/{$generation->id}/base.png";
            Storage::disk('public')->put($localPath, Http::timeout(120)->get($baseImageUrl)->body());

            $generation->update([
                'base_image_path' => $localPath,
                'ai_raw_response' => $aiResult['raw_response'],
                'ai_model' => $aiResult['model'],
            ]);

            $brandKit = $generation->project->brandKit
                ? $generation->project->brandKit->toArray()
                : [];
            $localAbsolutePath = Storage::disk('public')->path($localPath);
            $exports = app(MultiSizeRendererService::class)->renderFromAi(
                $localAbsolutePath,
                $brandKit,
                $inputForm,
                (string) $generation->id
            );

            foreach ($exports as $exportData) {
                $generation->exports()->create($exportData);
            }

            $generation->update(['status' => 'done']);
        } catch (\Exception $e) {
            Log::error('Ad Regeneration Failed', ['id' => $generation->id, 'error' => $e->getMessage()]);
            $generation->update(['status' => 'failed']);
        }

        return redirect()->route('metaadsimagegenerator.show', $generation->id)
            ->with('success', 'Regeneration complete.');
    }

    private function storeResizedImage($file, string $directory, int $maxDim = 1024): string
    {
        $originalPath = $file->getPathname();
        $imageInfo = @getimagesize($originalPath);

        if (!$imageInfo) {
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs($directory, $filename, 'public');
            return "{$directory}/{$filename}";
        }

        [$origW, $origH] = $imageInfo;
        $ratio = min($maxDim / $origW, $maxDim / $origH, 1);
        $newW = (int) ($origW * $ratio);
        $newH = (int) ($origH * $ratio);

        $image = match ($imageInfo['mime']) {
            'image/png' => imagecreatefrompng($originalPath),
            'image/webp' => imagecreatefromwebp($originalPath),
            default => imagecreatefromjpeg($originalPath),
        };

        if (!$image) {
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs($directory, $filename, 'public');
            return "{$directory}/{$filename}";
        }

        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $filename = uniqid() . '.webp';
        $path = "{$directory}/{$filename}";
        $fullPath = Storage::disk('public')->path($path);

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        imagewebp($resized, $fullPath, 82);

        imagedestroy($image);
        imagedestroy($resized);

        return $path;
    }
}
