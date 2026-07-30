<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\FonnteService;
use App\Services\SeoAgent\SeoAgentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SeoAgentController extends Controller
{
    public function __construct(
        protected FonnteService $fonnte,
        protected SeoAgentOrchestrator $orchestrator,
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        Log::info('SeoAgent webhook received', $request->all());

        // Verify webhook secret if configured
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

        // Check if sender is allowed
        if (!$this->fonnte->isAllowedNumber($sender)) {
            Log::warning('SeoAgent: unauthorized sender', ['sender' => $sender]);
            $this->fonnte->send($sender, "Maaf, nomor Anda tidak terdaftar untuk menggunakan layanan ini.");
            return response()->json(['status' => true, 'message' => 'unauthorized']);
        }

        // Rate limiting by sender
        if ($this->isRateLimited($sender)) {
            $this->fonnte->send($sender, "Mohon tunggu, Anda terlalu banyak mengirim pesan. Coba lagi nanti.");
            return response()->json(['status' => true, 'message' => 'rate limited']);
        }

        \App\Jobs\SeoAgentProcessCommandJob::dispatch($sender, $message, $name);

        return response()->json(['status' => true, 'message' => 'processed']);
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

        $recentCount = \App\Models\SeoAgentLog::where('sender', $sender)
            ->where('created_at', '>=', now()->subMinutes($decayMinutes))
            ->count();

        return $recentCount >= $maxAttempts;
    }
}
