<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\ApiKeyWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebApiKeyController extends Controller
{
    public function index()
    {
        $keys = Auth::user()->apiKeys()
            ->withCount(['websites' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $keys->getCollection()->each(function ($key) {
            $key->plain_text_key = $key->getDecryptedKey();
        });

        return view('dashboard.api_keys', ['keys' => $keys]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'expires_at' => 'nullable|date|after:today',
            'max_sites' => 'nullable|integer|min:1|max:1000',
        ]);

        $result = ApiKey::generate(
            $validated['name'],
            Auth::id(),
            $validated['expires_at'] ?? null,
            $validated['max_sites'] ?? null,
        );

        return redirect()->route('api-keys.index')
            ->with('success', 'API key created successfully!')
            ->with('plain_text_key', $result['plain_text']);
    }

    public function update(Request $request, ApiKey $apiKey)
    {
        abort_if($apiKey->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'max_sites' => 'nullable|integer|min:1|max:1000',
        ]);

        $apiKey->update($validated);

        return redirect()->route('api-keys.index')->with('success', 'API key updated.');
    }

    public function destroy(ApiKey $apiKey)
    {
        abort_if($apiKey->user_id !== Auth::id(), 403);
        $apiKey->delete();
        return redirect()->route('api-keys.index')->with('success', 'API key revoked.');
    }

    public function suspend(ApiKey $apiKey)
    {
        abort_if($apiKey->user_id !== Auth::id(), 403);
        if (!$apiKey->is_active) {
            return back()->with('error', 'API key is already suspended.');
        }
        $apiKey->update(['is_active' => false]);
        return redirect()->route('api-keys.index')->with('success', 'API key suspended.');
    }

    public function unsuspend(ApiKey $apiKey)
    {
        abort_if($apiKey->user_id !== Auth::id(), 403);
        if ($apiKey->is_active) {
            return back()->with('error', 'API key is already active.');
        }
        $apiKey->update(['is_active' => true]);
        return redirect()->route('api-keys.index')->with('success', 'API key reactivated.');
    }

    private function authorizeAccess(ApiKey $apiKey): void
    {
        $user = Auth::user();
        $isAdmin = $user->is_admin ?? false;
        abort_if($apiKey->user_id !== $user->id && !$isAdmin, 403);
    }

    public function showKey(ApiKey $apiKey)
    {
        $this->authorizeAccess($apiKey);

        $plain = $apiKey->getDecryptedKey();

        if (!$plain) {
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve the API key. You may need to regenerate it.',
            ]);
        }

        return response()->json([
            'success' => true,
            'key' => $plain,
        ]);
    }

    public function showDetail(ApiKey $apiKey)
    {
        $this->authorizeAccess($apiKey);

        $plain = $apiKey->getDecryptedKey();
        $websites = $apiKey->websites()->orderBy('last_used_at', 'desc')->get();

        $websiteData = $websites->map(function ($site) {
            $contentGens = \Modules\ContentGenerator\Models\ContentGeneration::where('api_key_website_id', $site->id)->count();
            $keywordResearches = \Modules\KeywordResearch\Models\KeywordResearch::where('api_key_website_id', $site->id)->count();

            return [
                'id' => $site->id,
                'domain' => $site->domain,
                'is_active' => $site->is_active,
                'last_used_at' => $site->last_used_at?->diffForHumans(),
                'last_ip' => $site->last_ip,
                'tokens_in' => (int) $site->tokens_in,
                'tokens_out' => (int) $site->tokens_out,
                'tokens_total' => (int) $site->tokens_total,
                'content_generations' => $contentGens,
                'keyword_researches' => $keywordResearches,
            ];
        });

        $totalTokensIn = $websites->sum('tokens_in');
        $totalTokensOut = $websites->sum('tokens_out');
        $totalTokens = $websites->sum('tokens_total');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $plain,
                'is_active' => $apiKey->is_active,
                'status' => $apiKey->status,
                'expires_at' => $apiKey->expires_at?->format('M d, Y'),
                'max_sites' => $apiKey->max_sites,
                'last_used_at' => $apiKey->last_used_at?->diffForHumans(),
                'websites' => $websiteData,
                'total_tokens_in' => (int) $totalTokensIn,
                'total_tokens_out' => (int) $totalTokensOut,
                'total_tokens' => (int) $totalTokens,
            ],
        ]);
    }

    public function websites(ApiKey $apiKey)
    {
        $this->authorizeAccess($apiKey);

        $websites = $apiKey->websites()
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json(['data' => $websites]);
    }

    public function toggleWebsite(ApiKey $apiKey, Request $request)
    {
        $this->authorizeAccess($apiKey);

        $validated = $request->validate([
            'domain' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        $website = $apiKey->websites()->where('domain', $validated['domain'])->firstOrFail();
        $website->update(['is_active' => $validated['is_active']]);

        $action = $validated['is_active'] ? 'unsuspended' : 'suspended';
        return redirect()->route('api-keys.index')->with('success', "Website {$action}.");
    }
}
