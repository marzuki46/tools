<?php

use Illuminate\Support\Facades\Route;
use Modules\ContentGenerator\Http\Controllers\ContentGeneratorController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/content-generator', [ContentGeneratorController::class, 'index'])
        ->name('contentgenerator.index');

    Route::get('/content-generator/create', [ContentGeneratorController::class, 'create'])
        ->name('contentgenerator.create');

    Route::post('/content-generator', [ContentGeneratorController::class, 'store'])
        ->name('contentgenerator.store');

    Route::get('/content-generator/{id}', [ContentGeneratorController::class, 'show'])
        ->name('contentgenerator.show');

    Route::get('/content-generator/{id}/status', [ContentGeneratorController::class, 'status'])
        ->name('contentgenerator.status');

    Route::delete('/content-generator/{id}', [ContentGeneratorController::class, 'destroy'])
        ->name('contentgenerator.destroy');

    Route::post('/content-generator/{id}/retry-phase/{phase}', [ContentGeneratorController::class, 'retryPhase'])
        ->name('contentgenerator.retry-phase');

    Route::post('/content-generator/{id}/feedback', [ContentGeneratorController::class, 'feedback'])
        ->name('contentgenerator.feedback');

    Route::post('/content-generator/{id}/generate-meta', [ContentGeneratorController::class, 'generateMeta'])
        ->name('contentgenerator.generate-meta');

    Route::get('/content-generator/queue-status', [ContentGeneratorController::class, 'queueStatus'])
        ->name('contentgenerator.queue-status');

    Route::post('/content-generator/queue-restart', [ContentGeneratorController::class, 'queueRestart'])
        ->name('contentgenerator.queue-restart');

    Route::post('/content-generator/retry-failed', [ContentGeneratorController::class, 'retryFailed'])
        ->name('contentgenerator.retry-failed');
});
