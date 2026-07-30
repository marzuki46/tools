<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
    public function checkStatus(Request $request)
    {
        $apiKey = $request->header('X-API-Key') ?? $request->bearerToken();

        if (!$apiKey) {
            return response()->json([
                'active' => false,
                'status' => 'invalid',
                'message' => 'API key is required.',
            ]);
        }

        $hashed = hash('sha256', $apiKey);
        $key = ApiKey::where('key', $hashed)->first();

        if (!$key) {
            return response()->json([
                'active' => false,
                'status' => 'invalid',
                'message' => 'Invalid API key.',
            ]);
        }

        if (!$key->is_active) {
            return response()->json([
                'active' => false,
                'status' => 'suspended',
                'message' => 'API key is suspended. Contact support.',
                'expires_at' => $key->expires_at?->toISOString(),
            ]);
        }

        if ($key->isExpired()) {
            return response()->json([
                'active' => false,
                'status' => 'expired',
                'message' => 'API key has expired. Renew your key.',
                'expires_at' => $key->expires_at?->toISOString(),
            ]);
        }

        $domain = $request->header('X-Site-Domain') ?: null;
        if (!$domain) {
            $origin = $request->header('Origin');
            $referer = $request->header('Referer');
            $domain = $origin ? parse_url($origin, PHP_URL_HOST) : ($referer ? parse_url($referer, PHP_URL_HOST) : null);
        }

        $websiteStatus = 'active';
        if ($domain) {
            $website = $key->websites()->where('domain', $domain)->first();
            if ($website && !$website->is_active) {
                $websiteStatus = 'blocked';
            }
        }

        return response()->json([
            'active' => true,
            'status' => 'active',
            'expires_at' => $key->expires_at?->toISOString(),
            'website_status' => $websiteStatus,
            'domain' => $domain,
            'max_sites' => $key->max_sites,
            'sites_used' => $key->websites()->count(),
            'message' => 'API key is active.',
        ]);
    }

    public function index()
    {
        $keys = Auth::user()->apiKeys()
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->makeHidden(['key']);

        return response()->json(['data' => $keys]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $result = ApiKey::generate(
            $validated['name'],
            Auth::id(),
            $validated['expires_at'] ?? null
        );

        return response()->json([
            'message' => 'API key created successfully.',
            'data' => [
                'api_key' => $result['api_key']->makeHidden(['key']),
                'plain_text_key' => $result['plain_text'],
            ],
        ], 201);
    }

    public function show(ApiKey $apiKey)
    {
        $this->authorizeKey($apiKey);
        return response()->json(['data' => $apiKey->makeHidden(['key'])]);
    }

    public function destroy(ApiKey $apiKey)
    {
        $this->authorizeKey($apiKey);
        $apiKey->delete();
        return response()->json(['message' => 'API key revoked.']);
    }

    public function regenerate(Request $request, ApiKey $apiKey)
    {
        $this->authorizeKey($apiKey);

        return DB::transaction(function () use ($apiKey) {
            $result = ApiKey::generate(
                $apiKey->name,
                Auth::id(),
                $apiKey->expires_at?->toDateTimeString()
            );

            $apiKey->delete();

            return response()->json([
                'message' => 'API key regenerated. Old key revoked.',
                'data' => [
                    'api_key' => $result['api_key']->makeHidden(['key']),
                    'plain_text_key' => $result['plain_text'],
                ],
            ]);
        });
    }

    private function authorizeKey(ApiKey $apiKey): void
    {
        abort_if($apiKey->user_id !== Auth::id(), 403, 'Forbidden.');
    }
}
