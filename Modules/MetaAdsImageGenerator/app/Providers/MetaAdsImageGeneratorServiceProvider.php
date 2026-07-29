<?php

namespace Modules\MetaAdsImageGenerator\Providers;

use Illuminate\Support\ServiceProvider;

class MetaAdsImageGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/meta-ads-image-generator.php', 'meta-ads-image-generator');

        $this->app->singleton('meta-ads-image-generator', function () {
            return new \Modules\MetaAdsImageGenerator\MetaAdsImageGenerator;
        });
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Load API routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        // Load Web routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'metaadsimagegenerator');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/meta-ads-image-generator.php' => config_path('meta-ads-image-generator.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }
    }
}
