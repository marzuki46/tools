<?php

namespace App\Http\Controllers;

use App\Models\SeoAnalysis;
use App\Services\SeoAnalyzerService;
use Illuminate\Http\Request;

class SeoAnalyzerController extends Controller
{
    public function index()
    {
        $analyses = SeoAnalysis::forUser(auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('seo-analyzer.index', compact('analyses'));
    }

    public function analyze(Request $request, SeoAnalyzerService $service)
    {
        $validated = $request->validate([
            'url' => 'required|url|max:500',
            'keyword' => 'nullable|string|max:255',
        ]);

        $url = $validated['url'];
        $keyword = $validated['keyword'] ?? null;

        $result = $service->analyze($url, $keyword);

        $analysis = SeoAnalysis::create([
            'user_id' => auth()->id(),
            'url' => $url,
            'keyword' => $keyword ?? '',
            'title' => $result['title'] ?? '',
            'meta_description' => $result['meta_description'] ?? '',
            'score' => $result['score'],
            'result' => $result,
        ]);

        return redirect()->route('seo-analyzer.show', $analysis->id);
    }

    public function show($id)
    {
        $analysis = SeoAnalysis::forUser(auth()->id())->findOrFail($id);
        return view('seo-analyzer.show', compact('analysis'));
    }

    public function destroy($id)
    {
        $analysis = SeoAnalysis::forUser(auth()->id())->findOrFail($id);
        $analysis->delete();
        return redirect()->route('seo-analyzer.index')
            ->with('success', 'Analisis berhasil dihapus.');
    }
}
