<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\WebsiteToolMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'api-key' => ApiKeyMiddleware::class,
            'website-tool' => WebsiteToolMiddleware::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->call(function () {
            Cache::put('queue_heartbeat', now()->toIso8601String(), 300);
            if (!\App\Models\Setting::workerEnabled()) {
                $pending = \DB::table('jobs')->count();
                if ($pending === 0) {
                    return;
                }

                \App\Models\Setting::setValue('queue.worker_enabled', '1');
                Log::info('Queue worker auto-enabled via scheduler (pending jobs found).', ['pending' => $pending]);
            }

            \Artisan::call('queue:work --queue=default,keyword-research,content-generator --stop-when-empty --timeout=620 --tries=3 --sleep=3');

            Cache::put('queue_heartbeat', now()->toIso8601String(), 300);
        })->name('queue-worker')->everyMinute()->withoutOverlapping(10);

        $schedule->command('seo-cluster:run')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
