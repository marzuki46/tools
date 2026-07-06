<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key') ?? $request->bearerToken();

        if (!$apiKey) {
            return response()->json([
                'message' => 'API key is required. Provide via X-API-Key header or Bearer token.',
            ], 401);
        }

        $key = ApiKey::authenticate($apiKey);

        if (!$key) {
            return response()->json([
                'message' => 'Invalid or expired API key.',
            ], 401);
        }

        auth()->login($key->user);
        $key->touchLastUsed($request->ip());

        return $next($request);
    }
}
