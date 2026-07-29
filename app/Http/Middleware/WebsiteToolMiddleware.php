<?php

namespace App\Http\Middleware;

use App\Models\Websites\WebsiteTool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebsiteToolMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Website-Key') ?? $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Website tool key is required. Provide via X-Website-Key header or Bearer token.',
            ], 401);
        }

        $websiteTool = WebsiteTool::authenticate($token);

        if (!$websiteTool) {
            return response()->json([
                'message' => 'Invalid or expired website tool key.',
            ], 401);
        }

        $websiteTool->touchLastUsed($request->ip());

        $request->merge([
            'website_tool' => $websiteTool,
            'website' => $websiteTool->website,
        ]);

        return $next($request);
    }
}
