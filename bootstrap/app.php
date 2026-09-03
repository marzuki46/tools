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
        // Denyut jantung TERPISAH dari worker. Tiap menit (tanpa overlapping,
        // tanpa diblokir worker yang sedang memproses job lama) tulis queue_heartbeat
        // SELAMA worker yang aktif masih "hidup" (worker_last_beat segar).
        // Tanpa ini, satu job AI yang lama (menit-an) membuat heartbeat basi -> UI
        // menampilkan "Berhenti / Macet" palsu padahal worker sedang bekerja.
        $schedule->call(function () {
            $beat = \App\Models\Setting::getValue('queue.worker_last_beat', null);
            if ($beat && \Illuminate\Support\Carbon::parse($beat)->diffInSeconds(now()) < 180) {
                Cache::put('queue_heartbeat', now()->toIso8601String(), 300);
            }
        })->everyMinute()->name('queue-beat');

        // Worker: jalankan bila ada job & belum ada worker lain yang hidup.
        // Setiap job yang diproses memperbarui worker_last_beat, jadi beat tetap
        // segar selama worker sehat; jika process mati di tengah, beat basi -> cron
        // menyalakan worker baru lagi (self-healing), tidak "macet" selamanya.
        $schedule->call(function () {
            if (\DB::table('jobs')->count() === 0) {
                return;
            }

            $beat = \App\Models\Setting::getValue('queue.worker_last_beat', null);
            $workerAlive = $beat && \Illuminate\Support\Carbon::parse($beat)->diffInSeconds(now()) < 120;
            if ($workerAlive) {
                return;
            }

            \App\Models\Setting::setValue('queue.worker_last_beat', now()->toIso8601String());
            try {
                \Artisan::call('queue:work --queue=default,keyword-research,content-generator --stop-when-empty --timeout=1800 --tries=3 --sleep=3');
            } finally {
                \App\Models\Setting::setValue('queue.worker_last_beat', null);
            }
        })->everyMinute()->name('queue-worker')->withoutOverlapping(5);

        $schedule->command('seo-cluster:run')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
