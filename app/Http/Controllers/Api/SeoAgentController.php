<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoAgentLog;
use App\Models\Setting;
use App\Services\FonnteService;
use App\Services\SeoAgent\CommandParser;
use App\Services\SeoAgent\SeoAgentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SeoAgentController extends Controller
{
    protected array $asyncCommands = ['TREND', 'RESEARCH', 'GENERATE_CONTENT', 'PUBLISH'];

    public function __construct(
        protected FonnteService $fonnte,
        protected SeoAgentOrchestrator $orchestrator,
        protected CommandParser $parser,
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        Log::info('SeoAgent webhook received', $request->all());

        if (!$this->fonnte->verifyWebhook($request->all())) {
            Log::warning('SeoAgent: invalid webhook signature');
            return response()->json(['status' => false, 'message' => 'Invalid signature'], 403);
        }

        $sender = $request->input('sender');
        $message = $request->input('message');
        $name = $request->input('name', '');

        if (empty($sender) || empty($message)) {
            return response()->json(['status' => false, 'message' => 'sender and message required'], 400);
        }

        if (!$this->fonnte->isAllowedNumber($sender)) {
            Log::warning('SeoAgent: unauthorized sender', ['sender' => $sender]);
            $this->fonnte->send($sender, "Maaf, nomor Anda tidak terdaftar untuk menggunakan layanan ini.");
            return response()->json(['status' => true, 'message' => 'unauthorized']);
        }

        if ($this->isRateLimited($sender)) {
            $this->fonnte->send($sender, "Mohon tunggu, Anda terlalu banyak mengirim pesan. Coba lagi nanti.");
            return response()->json(['status' => true, 'message' => 'rate limited']);
        }

        // Fast commands — proses langsung, reply langsung via WA
        // Heavy commands — dispatch ke queue, reply dikirim setelah job selesai
        $parsed = $this->parser->parse($message);
        $isAsync = $parsed && in_array($parsed['type'], $this->asyncCommands);

        if ($isAsync) {
            \App\Jobs\SeoAgentProcessCommandJob::dispatch($sender, $message, $name);
            return response()->json(['status' => true, 'message' => 'queued']);
        }

        // Fast command: proses synchronous
        try {
            $result = $this->orchestrator->handle($sender, $message, $name);
            return response()->json(['status' => true, 'message' => $result['reply'] ?? 'ok']);
        } catch (\Throwable $e) {
            Log::error('SeoAgent: sync command failed', [
                'sender' => $sender, 'message' => $message, 'error' => $e->getMessage(),
            ]);
            $this->fonnte->send($sender, "Maaf, terjadi kesalahan: " . $e->getMessage());
            return response()->json(['status' => true, 'message' => 'error']);
        }
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'required|string',
            'message' => 'required|string',
        ]);

        $result = $this->fonnte->send($validated['target'], $validated['message']);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 500);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }

    protected function isRateLimited(string $sender): bool
    {
        $maxAttempts = (int) Setting::getValue('seo-agent.rate_limit.max_attempts', config('seo-agent.rate_limit.max_attempts', 10));
        $decayMinutes = (int) Setting::getValue('seo-agent.rate_limit.decay_minutes', config('seo-agent.rate_limit.decay_minutes', 1));

        $recentCount = SeoAgentLog::where('sender', $sender)
            ->where('created_at', '>=', now()->subMinutes($decayMinutes))
            ->count();

        return $recentCount >= $maxAttempts;
    }
}
