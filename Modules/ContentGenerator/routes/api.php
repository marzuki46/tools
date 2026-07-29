<?php

use Illuminate\Support\Facades\Route;
use Modules\ContentGenerator\Http\Controllers\Api\ContentApiController;

Route::prefix('api/content-generator')->middleware(['api', 'auth'])->group(function () {
    Route::post('/generate', [ContentApiController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/generations/{id}', [ContentApiController::class, 'show']);
});
