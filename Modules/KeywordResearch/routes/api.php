<?php

use Illuminate\Support\Facades\Route;
use Modules\KeywordResearch\Http\Controllers\Api\WebhookController;

// Internal API (via Sanctum auth)
Route::prefix('api/keyword-research')->middleware(['api', 'auth', 'throttle:30,1'])->group(function () {
    Route::post('/webhook', WebhookController::class)->name('api.keywordresearch.webhook');
    Route::get('/status/{id}', [WebhookController::class, 'status'])->name('api.keywordresearch.status');
});

// External API (via API Key - X-API-Key header or Bearer token)
Route::prefix('api/v1/keyword-research')->middleware(['api', 'api-key'])->group(function () {
    Route::post('/research', [WebhookController::class, 'research'])->name('api.keywordresearch.v1.research')->middleware('throttle:10,1');
    Route::get('/research/{id}', [WebhookController::class, 'status'])->name('api.keywordresearch.v1.status');
});
