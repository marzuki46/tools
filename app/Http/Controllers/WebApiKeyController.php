<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WebApiKeyController extends Controller
{
    public function index()
    {
        $keys = Auth::user()->apiKeys()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('dashboard.api_keys', ['keys' => $keys]);
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

        return redirect()->route('api-keys.index')
            ->with('success', 'API key created successfully!')
            ->with('plain_text_key', $result['plain_text']);
    }

    public function destroy(ApiKey $apiKey)
    {
        abort_if($apiKey->user_id !== Auth::id(), 403);
        $apiKey->delete();
        return redirect()->route('api-keys.index')->with('success', 'API key revoked.');
    }
}
