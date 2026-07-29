<?php

use Illuminate\Support\Facades\Route;
use Modules\KeywordResearch\Http\Controllers\KeywordResearchController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/keyword-research', [KeywordResearchController::class, 'index'])
        ->name('keywordresearch.index');

    Route::get('/keyword-research/create', [KeywordResearchController::class, 'create'])
        ->name('keywordresearch.create');

    Route::post('/keyword-research', [KeywordResearchController::class, 'store'])
        ->name('keywordresearch.store');

    Route::get('/keyword-research/{id}', [KeywordResearchController::class, 'show'])
        ->name('keywordresearch.show');

    Route::delete('/keyword-research/{id}', [KeywordResearchController::class, 'destroy'])
        ->name('keywordresearch.destroy');

    Route::post('/keyword-research/{id}/retry', [KeywordResearchController::class, 'retry'])
        ->name('keywordresearch.retry');
});
