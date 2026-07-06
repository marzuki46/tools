<?php

use Illuminate\Support\Facades\Route;
use Modules\MetaAdsImageGenerator\Http\Controllers\Api\GenerateController;

Route::prefix('api/meta-ads')->middleware(['api', 'auth'])->group(function () {
    Route::post('/generate', [GenerateController::class, 'store']);
    Route::get('/generations/{id}', [GenerateController::class, 'show']);
});