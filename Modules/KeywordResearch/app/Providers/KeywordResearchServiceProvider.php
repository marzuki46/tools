<?php

namespace Modules\KeywordResearch\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\KeywordResearch\Models\KeywordResearch;
use Modules\KeywordResearch\Policies\KeywordResearchPolicy;

class KeywordResearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/keyword-research.php', 'keyword-research');

        $this->app->singleton('keyword-research', function () {
            return new \Modules\KeywordResearch\KeywordResearch;
        });
    }

    public function boot(): void
    {
        Gate::policy(KeywordResearch::class, KeywordResearchPolicy::class);

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'keywordresearch');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/keyword-research.php' => config_path('keyword-research.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }
    }
}
