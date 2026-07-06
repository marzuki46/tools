<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
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
