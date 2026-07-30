<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected string $token;
    protected string $apiUrl;

    public function __construct()
    {
        $this->token = Setting::getValue('seo-agent.fonnte.token', config('seo-agent.fonnte.token', ''));
        $this->apiUrl = Setting::getValue('seo-agent.fonnte.api_url', config('seo-agent.fonnte.api_url', 'https://api.fonnte.com'));
    }

    public function send(string $target, string $message): array
    {
        if (empty($this->token)) {
            Log::error('FonnteService: token not configured');
            return ['success' => false, 'message' => 'Fonnte token not configured'];
        }

        $maxLength = (int) Setting::getValue('seo-agent.max_message_length', config('seo-agent.max_message_length', 1500));
        if (mb_strlen($message) > $maxLength) {
            $message = mb_substr($message, 0, $maxLength - 3) . '...';
        }

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => $this->token,
            ])->post("{$this->apiUrl}/send", [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $body = $response->json();

            if ($response->failed() || !($body['status'] ?? false)) {
                Log::warning('FonnteService: send failed', [
                    'target' => $target,
                    'response' => $body,
                    'status' => $response->status(),
                ]);
                return ['success' => false, 'message' => $body['reason'] ?? 'Failed to send message'];
            }

            return ['success' => true, 'data' => $body];
        } catch (Exception $e) {
            Log::error('FonnteService: exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifyWebhook(array $payload): bool
    {
        $secret = Setting::getValue('seo-agent.fonnte.webhook_secret', config('seo-agent.fonnte.webhook_secret', ''));
        if (empty($secret)) {
            return true;
        }

        $signature = $payload['signature'] ?? '';
        $computed = hash_hmac('sha256', json_encode($payload), $secret);
        return hash_equals($computed, $signature);
    }

    public function isAllowedNumber(string $sender): bool
    {
        $allowedRaw = Setting::getValue('seo-agent.allowed_numbers', config('seo-agent.allowed_numbers', ''));
        $allowed = is_array($allowedRaw) ? $allowedRaw : explode(',', $allowedRaw ?? '');
        $allowed = array_map('trim', $allowed);
        $allowed = array_filter($allowed);

        if (empty($allowed)) {
            return true;
        }

        $normalized = preg_replace('/[^0-9]/', '', $sender);
        foreach ($allowed as $num) {
            $cleanNum = preg_replace('/[^0-9]/', '', $num);
            if ($cleanNum && str_ends_with($normalized, $cleanNum)) {
                return true;
            }
        }

        return false;
    }
}
