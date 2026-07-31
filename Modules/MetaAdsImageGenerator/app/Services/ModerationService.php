<?php

namespace Modules\MetaAdsImageGenerator\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModerationService
{
    public function checkContent(string $text): bool
    {
        $blockedWords = ['nsfw', 'illegal', 'violence', 'gore', 'explicit', 'hate speech', 'terrorism'];

        foreach ($blockedWords as $word) {
            if (stripos($text, $word) !== false) {
                Log::warning('Moderation: blocked word triggered', ['word' => $word]);
                return false;
            }
        }

        $apiKey = Setting::getValue('ai.openai.api_key')
            ?? config('meta-ads-image-generator.providers.openai.api_key')
            ?? config('meta-ads-generator.providers.openai.api_key');
        $url = rtrim((string) (Setting::getValue('ai.openai.url') ?: config('meta-ads-image-generator.providers.openai.url', 'https://api.openai.com/v1')), '/');

        if (!$apiKey) {
            return true;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post("{$url}/moderations", [
                    'input' => $text,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $flagged = $result['results'][0]['flagged'] ?? false;
                if ($flagged) {
                    Log::warning('Moderation: OpenAI flagged content', [
                        'categories' => $result['results'][0]['categories'] ?? [],
                    ]);
                }
                return !$flagged;
            }

            Log::warning('Moderation API call failed', ['status' => $response->status()]);
            return true;
        } catch (\Exception $e) {
            Log::warning('Moderation API exception', ['error' => $e->getMessage()]);
            return true;
        }
    }
}