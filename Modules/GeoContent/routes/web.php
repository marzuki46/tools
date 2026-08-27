<?php

use Illuminate\Support\Facades\Route;
use Modules\GeoContent\Http\Controllers\GeoContentController;

Route::middleware(['web', 'auth'])->prefix('geo-content')->name('geocontent.')->group(function () {
    Route::get('/', [GeoContentController::class, 'index'])->name('index');
    Route::get('/create', [GeoContentController::class, 'create'])->name('create');
    Route::post('/', [GeoContentController::class, 'store'])->name('store');
    Route::get('/{project}', [GeoContentController::class, 'show'])->name('show');
    Route::post('/{project}/fetch-facts', [GeoContentController::class, 'fetchFacts'])->name('fetchFacts');
    Route::post('/{project}/questions', [GeoContentController::class, 'generateQuestions'])->name('generateQuestions');
    Route::post('/{project}/generate', [GeoContentController::class, 'generate'])->name('generate');
    Route::post('/{project}/publish', [GeoContentController::class, 'publish'])->name('publish');
    Route::post('/{project}/review', [GeoContentController::class, 'review'])->name('review');
});
