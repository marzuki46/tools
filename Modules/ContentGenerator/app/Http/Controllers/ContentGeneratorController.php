<?php

namespace Modules\ContentGenerator\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ContentGenerator\Jobs\ProcessContentGenerationJob;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Models\GenerationMemory;
use Modules\KeywordResearch\Models\KeywordResearch;

class ContentGeneratorController extends Controller
{
    public function index()
    {
        $generations = ContentGeneration::with('apiKeyWebsite')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $uid = auth()->id();
        $stats = [
            'total' => ContentGeneration::where('user_id', $uid)->count(),
            'completed' => ContentGeneration::where('user_id', $uid)->where('status', 'completed')->count(),
            'pending' => ContentGeneration::where('user_id', $uid)->whereIn('status', ['draft', 'phase_1', 'phase_2', 'phase_3'])->count(),
            'failed' => ContentGeneration::where('user_id', $uid)->where('status', 'failed')->count(),
            'active_users' => 1,
            'queue_pending' => \DB::table('jobs')->count(),
            'queue_failed' => \DB::table('failed_jobs')->count(),
        ];

        $queueStatus = $this->checkQueueWorker();

        return view('contentgenerator::index', compact('generations', 'stats', 'queueStatus'));
    }

    private function checkQueueWorker(): array
    {
        $raw = cache()->get('queue_heartbeat');
        $heartbeat = is_string($raw) ? \Carbon\Carbon::parse($raw) : null;
        $now = now();
        $pendingJobs = \DB::table('jobs')->count();

        if ($heartbeat && $heartbeat->diffInSeconds($now) < 120) {
            return ['status' => 'running', 'color' => 'green', 'label' => 'Berjalan', 'lastBeat' => $heartbeat];
        }

        if ($pendingJobs > 0) {
            return ['status' => 'stuck', 'color' => 'red', 'label' => 'Berhenti / Macet', 'lastBeat' => $heartbeat];
        }

        return ['status' => 'idle', 'color' => 'yellow', 'label' => 'Idle (tidak aktif)', 'lastBeat' => $heartbeat];
    }

    public function queueStatus(): JsonResponse
    {
        return response()->json($this->checkQueueWorker());
    }

    public function queueRestart(): JsonResponse
    {
        $phpBin = env('PHP_BINARY', 'php');
        $timeout = env('QUEUE_TIMEOUT', 240);
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/queue-worker.log');
        $manualCmd = "$phpBin $artisan queue:work --timeout=$timeout --tries=3";

        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => "Jalankan manual via SSH:\n<code>$manualCmd</code>",
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
            cache()->forget('queue_heartbeat');

            if ($exitCode === 0) {
                return response()->json(['success' => true, 'message' => 'Queue worker dimulai.']);
            }
        } catch (\Exception $e) {
        }

        return response()->json([
            'success' => false,
            'message' => "Gagal menjalankan otomatis. Jalankan manual:\n<code>$manualCmd</code>",
            'manual' => $manualCmd,
        ]);
    }

    public function retryFailed(): JsonResponse
    {
        $count = \DB::table('failed_jobs')->count();
        \Artisan::call('queue:retry all');
        return response()->json([
            'success' => true,
            'message' => "{$count} job gagal dimasukkan kembali ke antrian.",
        ]);
    }

    public function ensureWorkerRunning(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $phpBin = env('PHP_BINARY', 'php');
        $timeout = env('QUEUE_TIMEOUT', 240);

        $raw = cache()->get('queue_heartbeat');
        $heartbeat = is_string($raw) ? \Carbon\Carbon::parse($raw) : null;

        if ($heartbeat && $heartbeat->diffInSeconds(now()) < 180) {
            return;
        }

        if (\DB::table('jobs')->count() === 0) {
            return;
        }

        try {
            $artisan = base_path('artisan');
            $logFile = storage_path('logs/queue-worker.log');

            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = "start /B \"{$phpBin}\" \"{$artisan}\" queue:work --timeout={$timeout} --queue=default > \"{$logFile}\" 2>&1";
            } else {
                $cmd = "nohup {$phpBin} {$artisan} queue:work --timeout={$timeout} --queue=default > {$logFile} 2>&1 &";
            }

            exec($cmd);
            \Illuminate\Support\Facades\Log::info('Queue worker auto-started for new content');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to auto-start queue worker', ['error' => $e->getMessage()]);
        }
    }

    public function create()
    {
        $completedKeywordIds = ContentGeneration::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->whereNotNull('keyword_research_id')
            ->pluck('keyword_research_id')
            ->toArray();

        $researches = KeywordResearch::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->whereNotIn('id', $completedKeywordIds)
            ->orderBy('created_at', 'desc')
            ->get();

        $profiles = \App\Models\BusinessProfile::forUser(auth()->id())->active()->orderBy('is_default', 'desc')->get();

        return view('contentgenerator::create', compact('researches', 'profiles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'tone' => 'nullable|string|max:50',
            'keyword_research_id' => 'nullable|exists:keyword_researches,id',
            'business_profile_id' => 'nullable|exists:business_profiles,id',
            'lsi_keywords' => 'nullable|array|max:50',
            'lsi_keywords.*' => 'nullable',
            'entities' => 'nullable|array|max:30',
            'entities.*' => 'nullable',
            'include_external_links' => 'nullable|boolean',
        ]);

        $lsiKeywords = $validated['lsi_keywords'] ?? [];
        $entities = $validated['entities'] ?? [];

        if ($validated['keyword_research_id'] ?? null) {
            $research = KeywordResearch::where('user_id', auth()->id())
                ->find($validated['keyword_research_id']);
            if ($research) {
                $lsiKeywords = array_merge($lsiKeywords, $research->lsi_keywords ?? []);
                $entities = array_merge($entities, $research->entities ?? []);
            }
        }

        $service = app(\Modules\ContentGenerator\Services\ContentGeneratorService::class);
        $locale = $service->resolveLocale($validated['locale'] ?? null, null, $validated['target_keyword']);

        $generation = ContentGeneration::create([
            'user_id' => auth()->id(),
            'target_keyword' => $validated['target_keyword'],
            'locale' => $locale,
            'tone' => $validated['tone'] ?? 'informative',
            'keyword_research_id' => $validated['keyword_research_id'] ?? null,
            'business_profile_id' => $validated['business_profile_id'] ?? null,
            'lsi_keywords' => $lsiKeywords,
            'entities' => $entities,
            'include_external_links' => $request->has('include_external_links') ? (bool) $request->input('include_external_links') : null,
            'status' => 'draft',
            'current_phase' => 0,
        ]);

        ProcessContentGenerationJob::dispatch($generation);

        $this->ensureWorkerRunning();

        return redirect()->route('contentgenerator.show', $generation->id)
            ->with('success', 'Konten sedang diproses.');
    }

    public function show($id)
    {
        $generation = ContentGeneration::where('user_id', auth()->id())->findOrFail($id);
        $memory = GenerationMemory::where('content_generation_id', $generation->id)->first();
        $schema = \App\Models\SchemaMarkup::where('sourceable_type', get_class($generation))
            ->where('sourceable_id', $generation->id)
            ->first();
        return view('contentgenerator::show', compact('generation', 'memory', 'schema'));
    }

    public function status($id): JsonResponse
    {
        $generation = ContentGeneration::where('user_id', auth()->id())->findOrFail($id);
        return response()->json([
            'status' => $generation->status,
            'current_phase' => $generation->current_phase,
            'is_done' => in_array($generation->status, ['completed', 'failed']),
        ]);
    }

    public function feedback(Request $request, $id): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|in:1,2,3,4,5',
            'is_reference' => 'boolean',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $generation = ContentGeneration::where('user_id', auth()->id())->findOrFail($id);
        $memory = GenerationMemory::where('content_generation_id', $generation->id)->first();

        if ($memory) {
            $memory->update([
                'quality_score' => $request->rating,
                'is_reference' => $request->boolean('is_reference'),
                'feedback' => $request->feedback,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $generation = ContentGeneration::where('user_id', auth()->id())->findOrFail($id);
        $generation->delete();
        return redirect()->route('contentgenerator.index')
            ->with('success', 'Konten dihapus.');
    }

    public function retryPhase($id, $phase)
    {
        $generation = ContentGeneration::where('user_id', auth()->id())->findOrFail($id);
        $phase = (int) $phase;

        if (!in_array($phase, [1, 2, 3], true)) {
            return redirect()->route('contentgenerator.show', $generation->id)
                ->with('error', 'Fase harus 1, 2, atau 3.');
        }

        if ($phase === 1) {
            $generation->update(['phase_1_content' => null, 'status' => 'draft', 'current_phase' => 0]);
        } elseif ($phase === 2) {
            $generation->update(['phase_2_questions' => null, 'status' => 'phase_1_complete', 'current_phase' => 1]);
        } elseif ($phase === 3) {
            $generation->update(['phase_3_content' => null, 'status' => 'phase_2_complete', 'current_phase' => 2]);
        }

        ProcessContentGenerationJob::dispatch($generation, $phase);
        $this->ensureWorkerRunning();

        return redirect()->route('contentgenerator.show', $generation->id)
            ->with('success', "Fase {$phase} sedang diproses ulang.");
    }

    public function generateMeta($id): \Illuminate\Http\RedirectResponse
    {
        $generation = ContentGeneration::where('user_id', auth()->id())->findOrFail($id);

        if (empty($generation->phase_3_content)) {
            return redirect()->route('contentgenerator.show', $generation->id)
                ->with('error', 'Fase 3 harus selesai dulu sebelum generate meta.');
        }

        try {
            $service = app(\Modules\ContentGenerator\Services\ContentGeneratorService::class);
            $meta = $service->generateMetaData(
                $generation->phase_3_content,
                $generation->target_keyword,
                $generation->locale ?? 'id'
            );
            $generation->update([
                'meta_title' => $meta['title'],
                'meta_description' => $meta['description'],
            ]);

            return redirect()->route('contentgenerator.show', $generation->id)
                ->with('success', 'Meta title & description berhasil digenerate.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Generate meta failed', [
                'id' => $generation->id, 'error' => $e->getMessage(),
            ]);
            return redirect()->route('contentgenerator.show', $generation->id)
                ->with('error', 'Gagal generate meta. Silakan coba lagi.');
        }
    }
}
