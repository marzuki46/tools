<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $token;
    protected string $apiUrl;

    public function __construct()
    {
        $this->token = Setting::getValue('seo-agent.telegram.token', config('seo-agent.telegram.token', ''));
        $this->apiUrl = 'https://api.telegram.org/bot' . $this->token;
    }

    public function send(string $chatId, string $message): array
    {
        if (empty($this->token)) {
            Log::error('TelegramService: token not configured');
            return ['success' => false, 'message' => 'Telegram token not configured'];
        }

        $maxLength = (int) Setting::getValue('seo-agent.max_message_length', config('seo-agent.max_message_length', 4000));
        if (mb_strlen($message) > $maxLength) {
            $message = mb_substr($message, 0, $maxLength - 3) . '...';
        }

        try {
            $response = Http::timeout(15)->post("{$this->apiUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

            $body = $response->json();

            if ($response->failed() || !($body['ok'] ?? false)) {
                Log::warning('TelegramService: send failed', [
                    'chat_id' => $chatId,
                    'response' => $body,
                ]);
                return ['success' => false, 'message' => $body['description'] ?? 'Failed to send message'];
            }

            return ['success' => true, 'data' => $body['result']];
        } catch (Exception $e) {
            Log::error('TelegramService: exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function setWebhook(string $url): array
    {
        try {
            $response = Http::timeout(15)->post("{$this->apiUrl}/setWebhook", [
                'url' => $url,
                'allowed_updates' => ['message'],
            ]);

            $body = $response->json();
            return ['success' => ($body['ok'] ?? false), 'data' => $body];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteWebhook(): array
    {
        try {
            $response = Http::timeout(15)->post("{$this->apiUrl}/deleteWebhook");
            $body = $response->json();
            return ['success' => ($body['ok'] ?? false), 'data' => $body];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getWebhookInfo(): array
    {
        try {
            $response = Http::timeout(15)->get("{$this->apiUrl}/getWebhookInfo");
            return $response->json()['result'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function isAllowedUser(int $userId): bool
    {
        $allowedRaw = Setting::getValue('seo-agent.allowed_numbers', config('seo-agent.allowed_numbers', ''));
        $allowed = is_array($allowedRaw) ? $allowedRaw : explode(',', $allowedRaw ?? '');
        $allowed = array_map('trim', $allowed);
        $allowed = array_filter($allowed);

        if (empty($allowed)) {
            return true;
        }

        return in_array((string) $userId, $allowed);
    }
}
