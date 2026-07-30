<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
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

    public function showKey(ApiKey $apiKey)
    {
        $user = Auth::user();

        $isAdmin = $user->hasRole('admin') ?? $user->is_admin ?? false;

        abort_if($apiKey->user_id !== $user->id && !$isAdmin, 403);

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

    public function websites(ApiKey $apiKey)
    {
        abort_if($apiKey->user_id !== Auth::id(), 403);

        $websites = $apiKey->websites()
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json(['data' => $websites]);
    }

    public function toggleWebsite(ApiKey $apiKey, Request $request)
    {
        abort_if($apiKey->user_id !== Auth::id(), 403);

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
