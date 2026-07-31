<?php

namespace Modules\AgentConnector\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AgentConnector\Services\AgentConnectorService;

class AgentConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/agent-connector.php', 'agent-connector');
        $this->app->singleton('agent-connector', function () {
            return new \Modules\AgentConnector\AgentConnector;
        });
        $this->app->singleton(AgentConnectorService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'agentconnector');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/agent-connector.php' => config_path('agent-connector.php'),
            ], 'config');
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }
    }
}
