<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiKeyWebsite;
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
            $status = $this->getKeyStatus($apiKey);
            return response()->json([
                'message' => $status === 'expired'
                    ? 'API key has expired. Please renew your key.'
                    : ($status === 'suspended'
                        ? 'API key is suspended. Contact support.'
                        : 'Invalid API key.'),
                'status' => $status,
            ], 401);
        }

        $domain = $this->detectDomain($request);
        if ($domain) {
            $website = $key->websites()->firstOrCreate(
                ['domain' => $domain],
                ['last_ip' => $request->ip()]
            );

            if (!$website->is_active) {
                return response()->json([
                    'message' => 'Website access blocked. Contact support.',
                    'status' => 'blocked',
                    'domain' => $domain,
                ], 403);
            }

            if ($key->max_sites && $key->websites()->where('is_active', true)->count() > $key->max_sites) {
                return response()->json([
                    'message' => 'Maximum number of websites reached for this API key.',
                    'status' => 'limit_reached',
                ], 403);
            }

            $website->update([
                'last_used_at' => now(),
                'last_ip' => $request->ip(),
            ]);
        }

        auth()->login($key->user);
        $key->touchLastUsed($request->ip());

        return $next($request);
    }

    private function detectDomain(Request $request): ?string
    {
        $domain = $request->header('X-Site-Domain');
        if ($domain) {
            return $this->normalizeDomain($domain);
        }

        $origin = $request->header('Origin');
        if ($origin) {
            $parsed = parse_url($origin, PHP_URL_HOST);
            if ($parsed) return $parsed;
        }

        $referer = $request->header('Referer');
        if ($referer) {
            $parsed = parse_url($referer, PHP_URL_HOST);
            if ($parsed) return $parsed;
        }

        return null;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        return $domain;
    }

    private function getKeyStatus(string $plainText): string
    {
        $hashed = hash('sha256', $plainText);
        $key = ApiKey::where('key', $hashed)->first();

        if (!$key) return 'invalid';
        if (!$key->is_active) return 'suspended';
        if ($key->isExpired()) return 'expired';

        return 'invalid';
    }
}
