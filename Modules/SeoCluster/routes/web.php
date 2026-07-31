<?php

use Illuminate\Support\Facades\Route;
use Modules\SeoCluster\Http\Controllers\SeoClusterController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/keyword-clusters', [SeoClusterController::class, 'index'])
        ->name('seocluster.index');

    Route::get('/keyword-clusters/create', [SeoClusterController::class, 'create'])
        ->name('seocluster.create');

    Route::post('/keyword-clusters/generate', [SeoClusterController::class, 'generate'])
        ->name('seocluster.generate');

    Route::post('/keyword-clusters', [SeoClusterController::class, 'store'])
        ->name('seocluster.store');

    Route::get('/keyword-clusters/{id}', [SeoClusterController::class, 'show'])
        ->name('seocluster.show');

    Route::get('/keyword-clusters/{id}/edit', [SeoClusterController::class, 'edit'])
        ->name('seocluster.edit');

    Route::put('/keyword-clusters/{id}', [SeoClusterController::class, 'update'])
        ->name('seocluster.update');

    Route::delete('/keyword-clusters/{id}', [SeoClusterController::class, 'destroy'])
        ->name('seocluster.destroy');

    Route::post('/keyword-clusters/{id}/activate', [SeoClusterController::class, 'activate'])
        ->name('seocluster.activate');

    Route::post('/keyword-clusters/{id}/pause', [SeoClusterController::class, 'pause'])
        ->name('seocluster.pause');

    Route::post('/keyword-clusters/{id}/keywords', [SeoClusterController::class, 'addKeyword'])
        ->name('seocluster.add-keyword');

    Route::delete('/keyword-clusters/{id}/keywords/{keywordId}', [SeoClusterController::class, 'removeKeyword'])
        ->name('seocluster.remove-keyword');

    Route::get('/keyword-clusters/{id}/progress', [SeoClusterController::class, 'progress'])
        ->name('seocluster.progress');
});
