<?php

namespace Modules\MetaAdsImageGenerator\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\MetaAdsImageGenerator\Models\AdPreset;

class PresetController extends Controller
{
    public function index()
    {
        $presets = AdPreset::where(function ($q) {
            $q->whereNull('user_id')->orWhere('user_id', Auth::id());
        })
            ->where('is_active', true)
            ->orderBy('user_id', 'asc')
            ->orderBy('name')
            ->paginate(20);

        return view('metaadsimagegenerator::presets.index', ['presets' => $presets]);
    }

    public function create()
    {
        return view('metaadsimagegenerator::presets.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'style_tag' => 'nullable|string|max:255',
            'prompt_template' => 'required|string',
        ]);

        AdPreset::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'style_tag' => $validated['style_tag'] ?? null,
            'prompt_template' => $validated['prompt_template'],
            'is_active' => true,
        ]);

        return redirect()->route('metaadsimagegenerator.presets.index')
            ->with('success', 'Preset created.');
    }

    public function edit(AdPreset $preset)
    {
        abort_if($preset->user_id !== Auth::id(), 403);
        return view('metaadsimagegenerator::presets.edit', ['preset' => $preset]);
    }

    public function update(Request $request, AdPreset $preset)
    {
        abort_if($preset->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'style_tag' => 'nullable|string|max:255',
            'prompt_template' => 'required|string',
        ]);

        $preset->update($validated);

        return redirect()->route('metaadsimagegenerator.presets.index')
            ->with('success', 'Preset updated.');
    }

    public function destroy(AdPreset $preset)
    {
        abort_if($preset->user_id !== Auth::id(), 403);
        $preset->delete();

        return redirect()->route('metaadsimagegenerator.presets.index')
            ->with('success', 'Preset deleted.');
    }
}
