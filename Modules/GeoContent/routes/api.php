<?php

use Illuminate\Support\Facades\Route;
use Modules\GeoContent\Http\Controllers\Api\GeoApiController;

Route::prefix('api/geo-content')->middleware(['api', 'auth:api'])->group(function () {
    Route::get('/projects', [GeoApiController::class, 'index']);
    Route::post('/projects', [GeoApiController::class, 'store']);
    Route::get('/projects/{project}', [GeoApiController::class, 'show']);
    Route::post('/projects/{project}/fetch-facts', [GeoApiController::class, 'fetchFacts']);
    Route::post('/projects/{project}/questions', [GeoApiController::class, 'generateQuestions']);
    Route::post('/projects/{project}/generate', [GeoApiController::class, 'generate']);
    Route::post('/projects/{project}/publish', [GeoApiController::class, 'publish']);
});
