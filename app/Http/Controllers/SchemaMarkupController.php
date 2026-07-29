<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\SchemaMarkup;
use App\Services\SchemaGeneratorService;
use Illuminate\Http\Request;

class SchemaMarkupController extends Controller
{
    public function index()
    {
        $markups = SchemaMarkup::forUser(auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('schema-markup.index', compact('markups'));
    }

    public function create(SchemaGeneratorService $service)
    {
        $types = SchemaMarkup::TYPES;

        $contents = \Modules\ContentGenerator\Models\ContentGeneration::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        $profiles = BusinessProfile::forUser(auth()->id())->active()->get();

        $autoFill = [];
        $selectedContent = null;
        $selectedType = request('type', 'Article');

        if (request('content_id')) {
            $selectedContent = \Modules\ContentGenerator\Models\ContentGeneration::where('user_id', auth()->id())
                ->find(request('content_id'));
            if ($selectedContent) {
                $profile = null;
                if ($selectedContent->business_profile_id) {
                    $profile = BusinessProfile::find($selectedContent->business_profile_id);
                }
                $autoFill = $service->autoFillFromContent($selectedType, $selectedContent, $profile);
                $autoFill['sourceable_id'] = $selectedContent->id;
                $autoFill['sourceable_type'] = get_class($selectedContent);
            }
        }

        return view('schema-markup.create', compact('types', 'contents', 'profiles', 'autoFill', 'selectedContent', 'selectedType'));
    }

    public function autoFill(Request $request, SchemaGeneratorService $service)
    {
        $validated = $request->validate([
            'schema_type' => 'required|string|in:' . implode(',', array_keys(SchemaMarkup::TYPES)),
            'content_id' => 'nullable|integer|exists:content_generations,id',
        ]);

        $data = [];

        if ($validated['content_id']) {
            $content = \Modules\ContentGenerator\Models\ContentGeneration::where('user_id', auth()->id())
                ->find($validated['content_id']);

            if ($content) {
                $profile = null;
                if ($content->business_profile_id) {
                    $profile = BusinessProfile::find($content->business_profile_id);
                }
                $data = $service->autoFillFromContent($validated['schema_type'], $content, $profile);
                $data['_content_id'] = $content->id;
                $data['_sourceable_type'] = get_class($content);
                $data['_content_title'] = $content->meta_title ?: $content->target_keyword;
                $data['_content_keyword'] = $content->target_keyword;
            }
        }

        return response()->json($data);
    }

    public function store(Request $request, SchemaGeneratorService $service)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'schema_type' => 'required|string|in:' . implode(',', array_keys(SchemaMarkup::TYPES)),
            'target_url' => 'nullable|url|max:500',
            'sourceable_type' => 'nullable|string|max:255',
            'sourceable_id' => 'nullable|integer',
            'use_ai' => 'boolean',
            'data' => 'required|array',
        ]);

        $useAi = $request->boolean('use_ai');

        $sourceable = null;
        if (!empty($validated['sourceable_type']) && !empty($validated['sourceable_id'])) {
            try {
                $sourceable = app($validated['sourceable_type'])::find($validated['sourceable_id']);
            } catch (\Exception $e) {
                $sourceable = null;
            }
        }

        $generated = $service->generate(
            $validated['schema_type'],
            $validated['data'],
            $validated['target_url'] ?? null,
            $sourceable,
            $useAi
        );

        $markup = SchemaMarkup::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'] ?? $validated['schema_type'] . ' - ' . ($validated['data']['name'] ?? $validated['data']['headline'] ?? now()->format('Y-m-d H:i')),
            'schema_type' => $validated['schema_type'],
            'target_url' => $validated['target_url'] ?? null,
            'sourceable_type' => $validated['sourceable_type'] ?? null,
            'sourceable_id' => $validated['sourceable_id'] ?? null,
            'data' => $validated['data'],
            'generated' => $generated,
            'use_ai' => $useAi,
        ]);

        return redirect()->route('schema-markup.show', $markup->id)
            ->with('success', 'Schema markup berhasil dibuat.');
    }

    public function show($id)
    {
        $markup = SchemaMarkup::forUser(auth()->id())->findOrFail($id);
        $jsonPretty = json_encode($markup->generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $scriptTag = $markup->toScriptTag();

        return view('schema-markup.show', compact('markup', 'jsonPretty', 'scriptTag'));
    }

    public function destroy($id)
    {
        $markup = SchemaMarkup::forUser(auth()->id())->findOrFail($id);
        $markup->delete();

        return redirect()->route('schema-markup.index')
            ->with('success', 'Schema markup berhasil dihapus.');
    }

    public function regenerate(Request $request, $id, SchemaGeneratorService $service)
    {
        $markup = SchemaMarkup::forUser(auth()->id())->findOrFail($id);

        $sourceable = null;
        if ($markup->sourceable_type && $markup->sourceable_id) {
            try {
                $sourceable = app($markup->sourceable_type)::find($markup->sourceable_id);
            } catch (\Exception $e) {
                $sourceable = null;
            }
        }

        $useAi = $request->boolean('use_ai', $markup->use_ai);

        $generated = $service->generate(
            $markup->schema_type,
            $markup->data,
            $markup->target_url,
            $sourceable,
            $useAi
        );

        $markup->update([
            'generated' => $generated,
            'use_ai' => $useAi,
        ]);

        return redirect()->route('schema-markup.show', $markup->id)
            ->with('success', 'Schema markup berhasil diperbarui.');
    }
}
