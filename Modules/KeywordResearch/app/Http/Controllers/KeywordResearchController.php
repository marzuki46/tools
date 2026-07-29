<?php

namespace Modules\KeywordResearch\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\KeywordResearch\Jobs\ProcessKeywordResearchJob;
use Modules\KeywordResearch\Models\KeywordResearch;

class KeywordResearchController extends Controller
{
    public function index()
    {
        $researches = KeywordResearch::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $stats = [
            'total' => KeywordResearch::where('user_id', auth()->id())->count(),
            'completed' => KeywordResearch::where('user_id', auth()->id())->where('status', 'completed')->count(),
            'pending' => KeywordResearch::where('user_id', auth()->id())->where('status', 'pending')->count(),
        ];

        return view('keywordresearch::index', compact('researches', 'stats'));
    }

    public function create()
    {
        return view('keywordresearch::create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'lsi_count' => 'nullable|integer|min:3|max:50',
            'entities_count' => 'nullable|integer|min:1|max:30',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:255',
        ]);

        $research = KeywordResearch::create([
            'user_id' => auth()->id(),
            'target_keyword' => $validated['target_keyword'],
            'locale' => $validated['locale'] ?? 'id',
            'lsi_count' => $validated['lsi_count'] ?? 12,
            'entities_count' => $validated['entities_count'] ?? 7,
            'status' => 'pending',
            'source' => $request->input('source', 'manual'),
            'webhook_url' => $validated['webhook_url'] ?? null,
            'webhook_secret' => $validated['webhook_secret'] ?? null,
        ]);

        ProcessKeywordResearchJob::dispatch($research);

        return redirect()->route('keywordresearch.show', $research->id)
            ->with('success', 'Riset keyword sedang diproses.');
    }

    public function show($id)
    {
        $research = KeywordResearch::where('user_id', auth()->id())->findOrFail($id);

        if ($research->status === 'pending') {
            ProcessKeywordResearchJob::dispatch($research);
        }

        return view('keywordresearch::show', compact('research'));
    }

    public function destroy($id)
    {
        $research = KeywordResearch::where('user_id', auth()->id())->findOrFail($id);
        $research->delete();

        return redirect()->route('keywordresearch.index')
            ->with('success', 'Riset keyword dihapus.');
    }

    public function retry($id)
    {
        $research = KeywordResearch::where('user_id', auth()->id())->findOrFail($id);

        if ($research->status === 'failed') {
            $research->update(['status' => 'pending', 'raw_response' => null]);
        }

        ProcessKeywordResearchJob::dispatch($research);

        return redirect()->route('keywordresearch.show', $research->id)
            ->with('success', 'Riset keyword sedang diproses ulang.');
    }
}
