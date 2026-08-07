<?php

namespace App\Http\Controllers;

use App\Models\Tools\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stats = [
            'total_keys' => $user->apiKeys()->count(),
            'active_keys' => $user->apiKeys()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count(),
            'tools_count' => Tool::active()->count(),
            'websites_count' => $user->websites()->count(),
            'active_website_tools' => $user->websites()
                ->where('websites.is_active', true)
                ->withCount(['tools' => fn ($q) => $q->where('website_tool.is_active', true)])
                ->get()
                ->sum('tools_count'),
        ];

        $recentKeys = $user->apiKeys()
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $queueStatus = $this->checkQueueWorker();

        return view('dashboard.index', [
            'stats' => $stats,
            'recent_keys' => $recentKeys,
            'queueStatus' => $queueStatus,
        ]);
    }

    public function queueStatus(): JsonResponse
    {
        return response()->json($this->checkQueueWorker());
    }

    public function queueStart(): JsonResponse
    {
        $phpBin = env('PHP_BINARY', 'ea-php84');
        $timeout = env('QUEUE_TIMEOUT', 240);
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/queue-worker.log');
        $queueArg = '--queue=default,keyword-research,content-generator --stop-when-empty --timeout=' . $timeout . ' --tries=3';
        $manualCmd = "$phpBin $artisan queue:work $queueArg";

        \App\Models\Setting::setValue('queue.worker_enabled', '1');

        $ranBackground = false;
        if (function_exists('exec') && !app()->environment('testing')) {
            try {
                if (PHP_OS_FAMILY === 'Windows') {
                    $cmd = "start /B \"{$phpBin}\" \"{$artisan}\" queue:work {$queueArg} > \"{$logFile}\" 2>&1";
                } else {
                    $cmd = "cd " . escapeshellarg(base_path()) . " && nohup {$phpBin} {$artisan} queue:work {$queueArg} > {$logFile} 2>&1 &";
                }
                exec($cmd, $output, $exitCode);
                Cache::forget('queue_heartbeat');

                if ($exitCode === 0) {
                    $ranBackground = true;
                }
            } catch (\Exception $e) {
            }
        }

        if ($ranBackground) {
            return response()->json([
                'success' => true,
                'message' => 'Worker dijalankan di background. Job akan diproses sekarang.',
            ]);
        }

        $pending = \DB::table('jobs')->count();
        if ($pending === 0) {
            return response()->json([
                'success' => true,
                'message' => 'Worker aktif, tapi tidak ada job dalam antrian.',
            ]);
        }

        try {
            set_time_limit((int) env('QUEUE_WEB_MAX_TIME', 55) + 10);
            \Artisan::call("queue:work --queue=default,keyword-research,content-generator --once --timeout={$timeout} --tries=3");
            Cache::put('queue_heartbeat', now()->toIso8601String(), 300);

            $remaining = \DB::table('jobs')->count();
            return response()->json([
                'success' => true,
                'message' => "1 job diproses sekarang. Sisa antrian: {$remaining} (diproses otomatis tiap menit oleh cron).",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Gagal menjalankan otomatis. Jalankan manual via terminal:\n<code>$manualCmd</code>",
                'manual' => $manualCmd,
            ]);
        }
    }

    public function queueToggle(): JsonResponse
    {
        $enabled = !static::workerEnabled();
        \App\Models\Setting::setValue('queue.worker_enabled', $enabled ? '1' : '0');

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled ? 'Worker diaktifkan. Job akan diproses otomatis tiap menit.' : 'Worker dimatikan. Job baru akan menunggu antrian.',
        ]);
    }

    public function queueRetryFailed(): JsonResponse
    {
        \Artisan::call('queue:retry all');
        return response()->json([
            'success' => true,
            'message' => 'Semua job gagal dikembalikan ke antrian.',
        ]);
    }

    public function queueClearFailed(): JsonResponse
    {
        \Artisan::call('queue:flush');
        return response()->json([
            'success' => true,
            'message' => 'Semua job gagal dibersihkan.',
        ]);
    }

    public static function workerEnabled(): bool
    {
        return \App\Models\Setting::workerEnabled();
    }

    private function checkQueueWorker(): array
    {
        $raw = Cache::get('queue_heartbeat');
        $heartbeat = is_string($raw) ? \Carbon\Carbon::parse($raw) : null;
        $now = now();
        $pendingJobs = \DB::table('jobs')->count();
        $failedJobs = \DB::table('failed_jobs')->count();

        $running = $heartbeat && $heartbeat->diffInSeconds($now) < 120;
        $enabled = static::workerEnabled();

        if (!$enabled) {
            $status = 'disabled';
            $color = 'gray';
            $label = 'Dimatikan';
        } else {
            $status = $running ? 'running' : ($pendingJobs > 0 ? 'stuck' : 'idle');
            $color = $running ? 'green' : ($pendingJobs > 0 ? 'red' : 'yellow');
            $label = $running ? 'Berjalan' : ($pendingJobs > 0 ? 'Berhenti / Macet' : 'Idle');
        }

        return [
            'status' => $status,
            'color' => $color,
            'label' => $label,
            'enabled' => $enabled,
            'lastBeat' => $heartbeat?->toIso8601String(),
            'lastBeatHuman' => $heartbeat ? $heartbeat->diffForHumans() : null,
            'pendingJobs' => $pendingJobs,
            'failedJobs' => $failedJobs,
            'cronCommand' => $this->cronCommand(),
        ];
    }

    private function cronCommand(): string
    {
        $php = env('WORKER_PHP_BINARY', 'ea-php84');
        $dir = env('WORKER_DIR', base_path());
        $log = storage_path('logs/schedule.log');

        return "* * * * * cd {$dir} && {$php} artisan schedule:run >> {$log} 2>&1";
    }
}
