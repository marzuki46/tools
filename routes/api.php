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

// External API routes (via API Key middleware)
Route::prefix('v1')->middleware(['api-key'])->group(function () {
    Route::get('/status', function () {
        return response()->json([
            'message' => 'API is running',
            'version' => '1.0',
        ]);
    });

    // Centralized tool API - dispatches to the appropriate module
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/tool/{tool}/{action}', [\App\Http\Controllers\Api\ToolApiController::class, 'execute']);
    });

    // Business Profiles API
    Route::get('/business-profiles', [\App\Http\Controllers\BusinessProfileController::class, 'apiList']);
    Route::post('/business-profiles', [\App\Http\Controllers\BusinessProfileController::class, 'apiStore']);
    Route::put('/business-profiles/{businessProfile}', [\App\Http\Controllers\BusinessProfileController::class, 'apiUpdate']);
    Route::delete('/business-profiles/{businessProfile}', [\App\Http\Controllers\BusinessProfileController::class, 'apiDestroy']);
});
