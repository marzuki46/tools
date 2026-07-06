<?php

use App\Http\Controllers\Api\ApiKeyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Protected routes (via Sanctum / web auth)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // API Key management
    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::get('/api-keys/{apiKey}', [ApiKeyController::class, 'show']);
    Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy']);
    Route::post('/api-keys/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate']);
});

// External API routes (via API Key middleware)
Route::prefix('v1')->middleware('api-key')->group(function () {
    // Tools will be registered here later
    Route::get('/status', function () {
        return response()->json([
            'message' => 'API is running',
            'version' => '1.0',
        ]);
    });
});
