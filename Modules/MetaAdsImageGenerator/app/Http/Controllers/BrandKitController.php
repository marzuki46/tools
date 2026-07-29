<?php

namespace Modules\MetaAdsImageGenerator\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\MetaAdsImageGenerator\Models\AdBrandKit;

class BrandKitController extends Controller
{
    public function index()
    {
        $brandKits = AdBrandKit::where('user_id', Auth::id())
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate(20);

        return view('metaadsimagegenerator::brand-kits.index', ['brandKits' => $brandKits]);
    }

    public function create()
    {
        return view('metaadsimagegenerator::brand-kits.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'primary_color' => 'required|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'font_family' => 'nullable|string|max:255',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            AdBrandKit::where('user_id', Auth::id())->update(['is_default' => false]);
        }

        $brandKit = AdBrandKit::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'primary_color' => $validated['primary_color'],
            'secondary_color' => $validated['secondary_color'] ?? null,
            'font_family' => $validated['font_family'] ?? null,
            'is_default' => $request->boolean('is_default'),
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('brand-kits/' . $brandKit->id, 'public');
            $brandKit->update(['logo_path' => $path]);
        }

        return redirect()->route('metaadsimagegenerator.brand-kits.index')
            ->with('success', 'Brand kit created.');
    }

    public function edit(AdBrandKit $brandKit)
    {
        abort_if($brandKit->user_id !== Auth::id(), 403);
        return view('metaadsimagegenerator::brand-kits.edit', ['brandKit' => $brandKit]);
    }

    public function update(Request $request, AdBrandKit $brandKit)
    {
        abort_if($brandKit->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'primary_color' => 'required|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'font_family' => 'nullable|string|max:255',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            AdBrandKit::where('user_id', Auth::id())
                ->where('id', '!=', $brandKit->id)
                ->update(['is_default' => false]);
        }

        $brandKit->update([
            'name' => $validated['name'],
            'primary_color' => $validated['primary_color'],
            'secondary_color' => $validated['secondary_color'] ?? null,
            'font_family' => $validated['font_family'] ?? null,
            'is_default' => $request->boolean('is_default'),
        ]);

        if ($request->hasFile('logo')) {
            if ($brandKit->logo_path) {
                Storage::disk('public')->delete($brandKit->logo_path);
            }
            $path = $request->file('logo')->store('brand-kits/' . $brandKit->id, 'public');
            $brandKit->update(['logo_path' => $path]);
        }

        return redirect()->route('metaadsimagegenerator.brand-kits.index')
            ->with('success', 'Brand kit updated.');
    }

    public function destroy(AdBrandKit $brandKit)
    {
        abort_if($brandKit->user_id !== Auth::id(), 403);

        if ($brandKit->logo_path) {
            Storage::disk('public')->delete($brandKit->logo_path);
        }

        $brandKit->delete();

        return redirect()->route('metaadsimagegenerator.brand-kits.index')
            ->with('success', 'Brand kit deleted.');
    }
}
