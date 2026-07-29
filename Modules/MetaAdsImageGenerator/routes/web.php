<?php

use Illuminate\Support\Facades\Route;
use Modules\MetaAdsImageGenerator\Http\Controllers\BrandKitController;
use Modules\MetaAdsImageGenerator\Http\Controllers\ExportController;
use Modules\MetaAdsImageGenerator\Http\Controllers\MetaAdsImageGeneratorController;
use Modules\MetaAdsImageGenerator\Http\Controllers\PresetController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::resource('meta-ads', MetaAdsImageGeneratorController::class)
        ->names('metaadsimagegenerator')
        ->parameters(['meta-ads' => 'metaAdsImageGenerator']);

    Route::post('/meta-ads/generate-copy', [MetaAdsImageGeneratorController::class, 'generateCopy'])
        ->name('metaadsimagegenerator.generate-copy');

    Route::post('/meta-ads/{id}/regenerate', [MetaAdsImageGeneratorController::class, 'regenerate'])
        ->name('metaadsimagegenerator.regenerate');

    Route::prefix('brand-kits')->name('metaadsimagegenerator.brand-kits.')->group(function () {
        Route::get('/', [BrandKitController::class, 'index'])->name('index');
        Route::get('/create', [BrandKitController::class, 'create'])->name('create');
        Route::post('/', [BrandKitController::class, 'store'])->name('store');
        Route::get('/{brandKit}/edit', [BrandKitController::class, 'edit'])->name('edit');
        Route::put('/{brandKit}', [BrandKitController::class, 'update'])->name('update');
        Route::delete('/{brandKit}', [BrandKitController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('presets')->name('metaadsimagegenerator.presets.')->group(function () {
        Route::get('/', [PresetController::class, 'index'])->name('index');
        Route::get('/create', [PresetController::class, 'create'])->name('create');
        Route::post('/', [PresetController::class, 'store'])->name('store');
        Route::get('/{preset}/edit', [PresetController::class, 'edit'])->name('edit');
        Route::put('/{preset}', [PresetController::class, 'update'])->name('update');
        Route::delete('/{preset}', [PresetController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('exports')->name('metaadsimagegenerator.exports.')->group(function () {
        Route::get('/{export}/download', [ExportController::class, 'download'])->name('download');
        Route::get('/generations/{generation}/zip', [ExportController::class, 'downloadZip'])->name('zip');
    });
});
