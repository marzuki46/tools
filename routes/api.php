<?php

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\WebsiteApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Protected routes (via Sanctum / web auth)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // API Key management
    Route::get('/api-keys', [ApiKeyController::class, 'index']);

    // Website management
    Route::apiResource('websites', WebsiteApiController::class)->names('api.websites');
    Route::post('/websites/{website}/attach-tool', [WebsiteApiController::class, 'attachTool']);
    Route::post('/websites/{website}/detach-tool', [WebsiteApiController::class, 'detachTool']);
    Route::post('/websites/{website}/generate-key', [WebsiteApiController::class, 'generateKey']);
    Route::post('/websites/{website}/regenerate-key', [WebsiteApiController::class, 'regenerateKey']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::get('/api-keys/{apiKey}', [ApiKeyController::class, 'show']);
    Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy']);
    Route::post('/api-keys/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate']);
});

// Key status check (no auth — works even for suspended/expired keys)
Route::get('/v1/key-status', [\App\Http\Controllers\Api\ApiKeyController::class, 'checkStatus']);

// SEO Agent webhook (public — Telegram sends updates here)
Route::post('/seo-agent/webhook', [\App\Http\Controllers\Api\SeoAgentController::class, 'webhook']);

// SEO Agent admin (protected by Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/seo-agent/set-webhook', [\App\Http\Controllers\Api\SeoAgentController::class, 'setWebhook']);
    Route::get('/seo-agent/webhook-info', [\App\Http\Controllers\Api\SeoAgentController::class, 'webhookInfo']);
    Route::post('/seo-agent/send', [\App\Http\Controllers\Api\SeoAgentController::class, 'send']);
});

// External API routes (via API Key middleware)
Route::prefix('v1')->middleware(['api-key'])->group(function () {
    Route::get('/status', function () {
        return response()->json([
            'message' => 'API is running',
            'version' => '1.0',
        ]);
    });

    // Centralized tool API - dispatches to the appropriate module
    // Rate limit dinaikkan jauh (1000/menit) karena alur batch + polling 24/7
    // (plugin kirim batch lalu poll status terus-menerus; 60/menit terlalu rendah
    //  dan memicu 429 'Too Many Attempts' saat batch besar).
    Route::middleware('throttle:1000,1')->group(function () {
        Route::post('/tool/{tool}/{action}', [\App\Http\Controllers\Api\ToolApiController::class, 'execute']);
    });

    // Business Profiles API
    Route::get('/business-profiles', [\App\Http\Controllers\BusinessProfileController::class, 'apiList']);
    Route::post('/business-profiles', [\App\Http\Controllers\BusinessProfileController::class, 'apiStore']);
    Route::put('/business-profiles/{businessProfile}', [\App\Http\Controllers\BusinessProfileController::class, 'apiUpdate']);
    Route::delete('/business-profiles/{businessProfile}', [\App\Http\Controllers\BusinessProfileController::class, 'apiDestroy']);

    // System Prompt (Custom Instructions)
    Route::get('/system-prompt', [\App\Http\Controllers\Api\SettingController::class, 'getSystemPrompt']);
    Route::put('/system-prompt', [\App\Http\Controllers\Api\SettingController::class, 'updateSystemPrompt']);
});
