<?php

namespace Modules\GeoContent\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\GeoContent\Models\GeoProject;
use Modules\GeoContent\Policies\GeoProjectPolicy;

class GeoContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/geo-content.php', 'geo-content');
        $this->app->singleton('geocontent', fn () => new \Modules\GeoContent\GeoContent);
        $this->app->singleton(\Modules\GeoContent\Services\GeoContentService::class);
        $this->app->singleton(\Modules\GeoContent\Services\CompetitorFactService::class);
        $this->app->singleton(\Modules\GeoContent\Services\BrandScrubberService::class);
        $this->app->singleton(\Modules\GeoContent\Services\CriticalQuestionService::class);
    }

    public function boot(): void
    {
        Gate::policy(GeoProject::class, GeoProjectPolicy::class);

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'geocontent');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/geo-content.php' => config_path('geo-content.php'),
            ], 'config');
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }
    }
}
