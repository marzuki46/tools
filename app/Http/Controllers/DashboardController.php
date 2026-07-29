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
        $phpBin = env('PHP_BINARY', 'php');
        $timeout = env('QUEUE_TIMEOUT', 240);
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/queue-worker.log');
        $manualCmd = "$phpBin $artisan queue:work --timeout=$timeout --tries=3";

        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => "Jalankan manual via SSH:\n<code>$manualCmd</code>\nAtau gunakan Supervisor/Horizon.",
                'manual' => $manualCmd,
            ]);
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = "start /B \"{$phpBin}\" \"{$artisan}\" queue:work --timeout={$timeout} > \"{$logFile}\" 2>&1";
            } else {
                $cmd = "nohup {$phpBin} {$artisan} queue:work --timeout={$timeout} --tries=3 > {$logFile} 2>&1 &";
            }

            exec($cmd, $output, $exitCode);
            Cache::forget('queue_heartbeat');

            if ($exitCode === 0) {
                return response()->json(['success' => true, 'message' => 'Queue worker dijalankan.']);
            }
        } catch (\Exception $e) {
        }

        return response()->json([
            'success' => false,
            'message' => "Gagal menjalankan otomatis. Jalankan manual via terminal:\n<code>$manualCmd</code>",
            'manual' => $manualCmd,
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

    private function checkQueueWorker(): array
    {
        $raw = Cache::get('queue_heartbeat');
        $heartbeat = is_string($raw) ? \Carbon\Carbon::parse($raw) : null;
        $now = now();
        $pendingJobs = \DB::table('jobs')->count();
        $failedJobs = \DB::table('failed_jobs')->count();

        $running = $heartbeat && $heartbeat->diffInSeconds($now) < 120;

        return [
            'status' => $running ? 'running' : ($pendingJobs > 0 ? 'stuck' : 'idle'),
            'color' => $running ? 'green' : ($pendingJobs > 0 ? 'red' : 'yellow'),
            'label' => $running ? 'Berjalan' : ($pendingJobs > 0 ? 'Berhenti / Macet' : 'Idle'),
            'lastBeat' => $heartbeat?->toIso8601String(),
            'lastBeatHuman' => $heartbeat ? $heartbeat->diffForHumans() : null,
            'pendingJobs' => $pendingJobs,
            'failedJobs' => $failedJobs,
        ];
    }
}
