<?php

namespace Modules\SeoCluster\Providers;

use Gate;
use Illuminate\Support\ServiceProvider;
use Modules\SeoCluster\Console\Commands\RunAutoClusterCommand;
use Modules\SeoCluster\Models\KeywordCluster;
use Modules\SeoCluster\Policies\KeywordClusterPolicy;

class SeoClusterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/seo-cluster.php', 'seo-cluster');
        $this->app->singleton('seo-cluster', function () {
            return new \Modules\SeoCluster\SeoCluster;
        });
        $this->app->singleton(\Modules\SeoCluster\Services\AutoClusterAgent::class);
        $this->app->singleton(\Modules\SeoCluster\Services\ClusterService::class);
    }

    public function boot(): void
    {
        Gate::policy(KeywordCluster::class, KeywordClusterPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunAutoClusterCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'seocluster');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/seo-cluster.php' => config_path('seo-cluster.php'),
            ], 'config');
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }
    }
}
