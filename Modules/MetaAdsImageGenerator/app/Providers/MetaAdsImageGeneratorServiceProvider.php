<?php

namespace Modules\MetaAdsImageGenerator\Providers;

use Illuminate\Support\ServiceProvider;

class MetaAdsImageGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/meta-ads-image-generator.php', 'meta-ads-image-generator'
        );

        $this->app->singleton('meta-ads-image-generator', function ($app) {
            return new MetaAdsImageGenerator();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/meta-ads-image-generator.php' => config_path('meta-ads-image-generator.php'),
            ], 'meta-ads-image-generator-config');

            $this->publishes([
                __DIR__ . '/../../database/migrations/' => database_path('migrations'),
            ], 'meta-ads-image-generator-migrations');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
    }
}