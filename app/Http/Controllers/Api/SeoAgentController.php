<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoAgentLog;
use App\Models\Setting;
use App\Services\SeoAgent\CommandParser;
use App\Services\SeoAgent\SeoAgentOrchestrator;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SeoAgentController extends Controller
{
    public function __construct(
        protected TelegramService $telegram,
        protected SeoAgentOrchestrator $orchestrator,
        protected CommandParser $parser,
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        $update = $request->all();
        Log::info('SeoAgent: Telegram update', ['update_id' => $update['update_id'] ?? null]);

        $message = $update['message'] ?? null;
        if (!$message || empty($message['text'])) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'];
        $text = $message['text'];
        $name = $message['from']['first_name'] ?? '';
        $isBot = $message['from']['is_bot'] ?? false;

        if ($isBot) {
            return response()->json(['ok' => true]);
        }

        if (!$this->telegram->isAllowedUser($chatId)) {
            Log::warning('SeoAgent: unauthorized chat', ['chat_id' => $chatId]);
            $this->telegram->send($chatId, "Maaf, chat ID Anda tidak terdaftar.");
            return response()->json(['ok' => true]);
        }

        if ($this->isRateLimited((string) $chatId)) {
            $this->telegram->send($chatId, "Mohon tunggu, terlalu banyak permintaan.");
            return response()->json(['ok' => true]);
        }

        $parsed = $this->parser->parse($text);
        $chatIdStr = (string) $chatId;

        // Kirim ack biar user tau perintah diterima
        if ($parsed && $parsed['type'] !== 'HELP') {
            $this->telegram->send($chatIdStr, "⏳ Perintah diterima! Sedang diproses...");
        }

        // Proses synchronous — queue worker bisa diaktifkan nanti
        // dengan memasukkan command ke $asyncCommands & setup cron.
        try {
            $this->orchestrator->handle($chatIdStr, $text, $name);
        } catch (\Throwable $e) {
            Log::error('SeoAgent: sync command failed', [
                'chat_id' => $chatId, 'text' => $text, 'error' => $e->getMessage(),
            ]);
            $this->telegram->send($chatIdStr, "Maaf, terjadi kesalahan: " . $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function setWebhook(Request $request): JsonResponse
    {
        $url = $request->input('url');
        if (!$url) {
            $url = url('/api/seo-agent/webhook');
        }

        $result = $this->telegram->setWebhook($url);

        return response()->json($result);
    }

    public function webhookInfo(): JsonResponse
    {
        return response()->json($this->telegram->getWebhookInfo());
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_id' => 'required|string',
            'message' => 'required|string',
        ]);

        $result = $this->telegram->send($validated['chat_id'], $validated['message']);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 500);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }

    protected function isRateLimited(string $chatId): bool
    {
        $maxAttempts = (int) Setting::getValue('seo-agent.rate_limit.max_attempts', config('seo-agent.rate_limit.max_attempts', 10));
        $decayMinutes = (int) Setting::getValue('seo-agent.rate_limit.decay_minutes', config('seo-agent.rate_limit.decay_minutes', 1));

        $recentCount = SeoAgentLog::where('sender', $chatId)
            ->where('created_at', '>=', now()->subMinutes($decayMinutes))
            ->count();

        return $recentCount >= $maxAttempts;
    }
}
