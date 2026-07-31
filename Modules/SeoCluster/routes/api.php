<?php

use Illuminate\Support\Facades\Route;
use Modules\SeoCluster\Http\Controllers\Api\SeoClusterApiController;

Route::prefix('api/keyword-clusters')->middleware(['api', 'auth'])->group(function () {
    Route::get('/', [SeoClusterApiController::class, 'index']);
    Route::post('/', [SeoClusterApiController::class, 'store']);
    Route::get('/{id}', [SeoClusterApiController::class, 'show']);
    Route::get('/{id}/progress', [SeoClusterApiController::class, 'progress']);
    Route::post('/{id}/activate', [SeoClusterApiController::class, 'activate']);
    Route::post('/{id}/pause', [SeoClusterApiController::class, 'pause']);
    Route::get('/automation/summary', [SeoClusterApiController::class, 'summary']);
});
