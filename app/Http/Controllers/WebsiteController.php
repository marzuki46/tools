<?php

namespace App\Http\Controllers;

use App\Models\ApiKeyWebsite;
use App\Models\Tools\Tool;
use App\Models\Websites\Website;
use App\Models\Websites\WebsiteTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebsiteController extends Controller
{
    public function index()
    {
        $apiKeyIds = Auth::user()->apiKeys()->pluck('id');

        $websites = ApiKeyWebsite::with('apiKey')
            ->whereIn('api_key_id', $apiKeyIds)
            ->orderBy('last_used_at', 'desc')
            ->paginate(20);

        return view('dashboard.websites.index', ['websites' => $websites]);
    }

    public function create()
    {
        return view('dashboard.websites.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $existing = Auth::user()->websites()
            ->where('domain', $validated['domain'])
            ->exists();

        if ($existing) {
            return back()->withErrors(['domain' => 'You already registered this domain.'])->withInput();
        }

        Auth::user()->websites()->create($validated);

        return redirect()->route('websites.index')
            ->with('success', 'Website registered successfully.');
    }

    public function show(Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $website->load(['tools' => function ($q) {
            $q->withPivot('is_active', 'config', 'api_key_hash', 'last_used_at', 'last_ip');
        }]);

        $allTools = Tool::active()->orderBy('name')->get();

        return view('dashboard.websites.show', [
            'website' => $website,
            'allTools' => $allTools,
        ]);
    }

    public function edit(Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        return view('dashboard.websites.edit', ['website' => $website]);
    }

    public function update(Request $request, Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $existing = Auth::user()->websites()
            ->where('domain', $validated['domain'])
            ->where('id', '!=', $website->id)
            ->exists();

        if ($existing) {
            return back()->withErrors(['domain' => 'Domain already registered.'])->withInput();
        }

        $website->update($validated);

        return redirect()->route('websites.show', $website)
            ->with('success', 'Website updated.');
    }

    public function destroy(Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $website->delete();

        return redirect()->route('websites.index')
            ->with('success', 'Website removed.');
    }

    public function toggleTool(Request $request, Website $website, Tool $tool)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $pivot = $website->tools()->where('tool_id', $tool->id)->first();

        if ($pivot) {
            $website->tools()->detach($tool->id);
            $message = "{$tool->name} removed from {$website->domain}.";
        } else {
            $website->tools()->attach($tool->id, ['is_active' => true]);
            $message = "{$tool->name} added to {$website->domain}.";
        }

        return redirect()->route('websites.show', $website)
            ->with('success', $message);
    }

    public function generateKey(Request $request, Website $website, Tool $tool)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $result = WebsiteTool::generateApiKey($website->id, $tool->id);

        return redirect()->route('websites.show', $website)
            ->with('success', 'API key generated for ' . $tool->name . '!')
            ->with('plain_text_key', $result['plain_text']);
    }

    public function regenerateKey(Request $request, Website $website, Tool $tool)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $result = WebsiteTool::generateApiKey($website->id, $tool->id);

        return redirect()->route('websites.show', $website)
            ->with('success', 'API key regenerated for ' . $tool->name . '!')
            ->with('plain_text_key', $result['plain_text']);
    }
}
