<?php

use App\Http\Controllers\Admin\ApiGuideController as AdminApiGuideController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\SchemaMarkupController;
use App\Http\Controllers\SeoAnalyzerController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\WebApiKeyController;

use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Public widget routes (no auth required)
Route::get('/widget/{toolSlug}/{apiKey}.js', [WidgetController::class, 'js'])->name('widget.js');
Route::get('/widget/{toolSlug}/{apiKey}/frame', [WidgetController::class, 'frame'])->name('widget.frame');
Route::get('/api/widget/{toolSlug}/{apiKey}/snippet', [WidgetController::class, 'snippet'])->name('widget.snippet');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/api-keys', [WebApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/api-keys', [WebApiKeyController::class, 'store'])->name('api-keys.store');
    Route::put('/api-keys/{apiKey}', [WebApiKeyController::class, 'update'])->name('api-keys.update');
    Route::delete('/api-keys/{apiKey}', [WebApiKeyController::class, 'destroy'])->name('api-keys.destroy');
    Route::post('/api-keys/{apiKey}/suspend', [WebApiKeyController::class, 'suspend'])->name('api-keys.suspend');
    Route::post('/api-keys/{apiKey}/unsuspend', [WebApiKeyController::class, 'unsuspend'])->name('api-keys.unsuspend');
    Route::get('/api-keys/{apiKey}/detail', [WebApiKeyController::class, 'showDetail'])->name('api-keys.detail');
    Route::get('/api-keys/{apiKey}/websites', [WebApiKeyController::class, 'websites'])->name('api-keys.websites');
    Route::post('/api-keys/{apiKey}/toggle-website', [WebApiKeyController::class, 'toggleWebsite'])->name('api-keys.toggle-website');

    Route::get('/documentation', [DocumentationController::class, 'index'])->name('documentation.index');

    Route::get('/business-profiles', [BusinessProfileController::class, 'index'])->name('business-profiles.index');
    Route::get('/business-profiles/create', [BusinessProfileController::class, 'create'])->name('business-profiles.create');
    Route::post('/business-profiles', [BusinessProfileController::class, 'store'])->name('business-profiles.store');
    Route::get('/business-profiles/{businessProfile}/edit', [BusinessProfileController::class, 'edit'])->name('business-profiles.edit');
    Route::put('/business-profiles/{businessProfile}', [BusinessProfileController::class, 'update'])->name('business-profiles.update');
    Route::delete('/business-profiles/{businessProfile}', [BusinessProfileController::class, 'destroy'])->name('business-profiles.destroy');

    Route::get('/tools', [ToolController::class, 'index'])->name('tools.index');
    Route::post('/tools/{tool}/toggle', [ToolController::class, 'toggle'])->name('tools.toggle');

    Route::get('/seo-analyzer', [SeoAnalyzerController::class, 'index'])->name('seo-analyzer.index');
    Route::post('/seo-analyzer', [SeoAnalyzerController::class, 'analyze'])->name('seo-analyzer.analyze');
    Route::get('/seo-analyzer/{id}', [SeoAnalyzerController::class, 'show'])->name('seo-analyzer.show');
    Route::delete('/seo-analyzer/{id}', [SeoAnalyzerController::class, 'destroy'])->name('seo-analyzer.destroy');

    Route::get('/schema-markup', [SchemaMarkupController::class, 'index'])->name('schema-markup.index');
    Route::get('/schema-markup/create', [SchemaMarkupController::class, 'create'])->name('schema-markup.create');
    Route::post('/schema-markup/autofill', [SchemaMarkupController::class, 'autoFill'])->name('schema-markup.autofill');
    Route::post('/schema-markup', [SchemaMarkupController::class, 'store'])->name('schema-markup.store');
    Route::get('/schema-markup/{id}', [SchemaMarkupController::class, 'show'])->name('schema-markup.show');
    Route::post('/schema-markup/{id}/regenerate', [SchemaMarkupController::class, 'regenerate'])->name('schema-markup.regenerate');
    Route::delete('/schema-markup/{id}', [SchemaMarkupController::class, 'destroy'])->name('schema-markup.destroy');

    Route::get('/websites', [WebsiteController::class, 'index'])->name('websites.index');
    Route::get('/websites/create', [WebsiteController::class, 'create'])->name('websites.create');
    Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
    Route::get('/websites/{website}', [WebsiteController::class, 'show'])->name('websites.show');
    Route::get('/websites/{website}/edit', [WebsiteController::class, 'edit'])->name('websites.edit');
    Route::put('/websites/{website}', [WebsiteController::class, 'update'])->name('websites.update');
    Route::delete('/websites/{website}', [WebsiteController::class, 'destroy'])->name('websites.destroy');
    Route::post('/websites/{website}/toggle-tool/{tool}', [WebsiteController::class, 'toggleTool'])->name('websites.toggle-tool');
    Route::post('/websites/{website}/generate-key/{tool}', [WebsiteController::class, 'generateKey'])->name('websites.generate-key');
    Route::post('/websites/{website}/regenerate-key/{tool}', [WebsiteController::class, 'regenerateKey'])->name('websites.regenerate-key');

    // Queue monitoring
    Route::get('/queue/status', [DashboardController::class, 'queueStatus'])->name('queue.status');
    Route::post('/queue/start', [DashboardController::class, 'queueStart'])->name('queue.start');
    Route::post('/queue/toggle', [DashboardController::class, 'queueToggle'])->name('queue.toggle');
    Route::post('/queue/retry-failed', [DashboardController::class, 'queueRetryFailed'])->name('queue.retry-failed');
    Route::post('/queue/clear-failed', [DashboardController::class, 'queueClearFailed'])->name('queue.clear-failed');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/api-guide', [AdminApiGuideController::class, 'index'])->name('api-guide');
        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings');
        Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/provider', [AdminSettingController::class, 'addProvider'])->name('settings.provider');
        Route::post('/settings/provider/delete', [AdminSettingController::class, 'removeProvider'])->name('settings.provider.delete');
        Route::post('/settings/telegram-webhook', [AdminSettingController::class, 'setTelegramWebhook'])->name('settings.telegram-webhook');
        Route::get('/settings/telegram-webhook-info', [AdminSettingController::class, 'telegramWebhookInfo'])->name('settings.telegram-webhook-info');

        Route::get('/tools', [ToolController::class, 'adminIndex'])->name('tools');
        Route::post('/tools/{tool}/toggle-active', [ToolController::class, 'adminToggleActive'])->name('tools.toggle-active');
        Route::get('/tools/{tool}/users', [ToolController::class, 'adminUsers'])->name('tools.users');
        Route::post('/tools/{tool}/users/{user}/toggle', [ToolController::class, 'adminToggleUser'])->name('tools.toggle-user');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/unsuspend', [AdminUserController::class, 'unsuspend'])->name('users.unsuspend');
        Route::post('/users/{user}/toggle-tool/{tool}', [AdminUserController::class, 'toggleTool'])->name('users.toggle-tool');
        Route::get('/users/{user}/api-keys', [AdminUserController::class, 'apiKeys'])->name('users.api-keys');
    });
});
