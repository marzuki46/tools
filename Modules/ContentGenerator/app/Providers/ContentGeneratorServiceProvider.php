<?php

namespace Modules\ContentGenerator\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Policies\ContentGenerationPolicy;

class ContentGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/content-generator.php', 'content-generator');
        $this->app->singleton('content-generator', function () {
            return new \Modules\ContentGenerator\ContentGenerator;
        });
    }

    public function boot(): void
    {
        Gate::policy(ContentGeneration::class, ContentGenerationPolicy::class);

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'contentgenerator');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/content-generator.php' => config_path('content-generator.php'),
            ], 'config');
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }
    }
}
