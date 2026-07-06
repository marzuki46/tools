<?php

use Illuminate\Support\Facades\Route;
use Modules\MetaAdsImageGenerator\Http\Controllers\MetaAdsImageGeneratorController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('metaadsimagegenerators', MetaAdsImageGeneratorController::class)->names('metaadsimagegenerator');
});
