<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\WebApiKeyController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // API Keys
    Route::get('/api-keys', [WebApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/api-keys', [WebApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('/api-keys/{apiKey}', [WebApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    // Tools
    Route::get('/tools', [ToolController::class, 'index'])->name('tools.index');
    Route::post('/tools/{tool}/toggle', [ToolController::class, 'toggle'])->name('tools.toggle');

    // Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/tools', [ToolController::class, 'adminIndex'])->name('tools');
        Route::post('/tools/{tool}/toggle-active', [ToolController::class, 'adminToggleActive'])->name('tools.toggle-active');
        Route::get('/tools/{tool}/users', [ToolController::class, 'adminUsers'])->name('tool-users');
        Route::post('/tools/{tool}/users/{user}/toggle', [ToolController::class, 'adminToggleUser'])->name('tool-users.toggle');
    });
});
