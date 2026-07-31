<?php

namespace Modules\MetaAdsImageGenerator\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiProviderManager
{
    protected function cfg(string $key, mixed $default = null): mixed
    {
        $db = Setting::getValue($key);
        if ($db !== null) {
            return $db;
        }
        return config("meta-ads-image-generator.{$key}", $default);
    }

    protected function providerCfg(string $provider, string $key): mixed
    {
        $db = Setting::providerConfig($provider);
        if (!empty($db[$key])) {
            return $db[$key];
        }
        return config("meta-ads-image-generator.providers.{$provider}.{$key}");
    }

    public function generateImage(string $prompt, ?string $provider = null, ?string $modelOverride = null): array
    {
        $provider = $provider ?? 'pollinations';

        return match ($provider) {
            'openai' => $this->generateWithOpenAi($prompt, $modelOverride),
            'stability' => $this->generateWithStability($prompt, $modelOverride),
            'pollinations' => $this->generateWithPollinations($prompt),
            default => throw new Exception("AI Provider [{$provider}] not supported."),
        };
    }

    protected function generateWithPollinations(string $prompt): array
    {
        $url = Setting::getValue('ai.9router.url', config('meta-ads-image-generator.providers.9router.url'));
        $apiKey = Setting::getValue('ai.9router.api_key', config('meta-ads-image-generator.providers.9router.api_key'));
        $model = 'cf/@cf/black-forest-labs/flux-1-schnell';

        if (!$url) {
            throw new Exception("9Router URL is missing.");
        }

        Log::info('9Router FLUX: generating image', ['prompt' => $prompt, 'model' => $model]);

        $endpoint = str_ends_with(rtrim($url, '/'), '/v1') ? rtrim($url, '/') . '/images/generations' : rtrim($url, '/') . '/v1/images/generations';

        $response = Http::timeout(120)->withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post($endpoint, [
            'model' => $model,
            'prompt' => $prompt,
            'size' => '1024x1024',
        ]);

        if ($response->failed()) {
            Log::error('9Router FLUX Image Gen Failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new Exception('Failed to generate image with 9Router FLUX: ' . $response->body());
        }

        $data = $response->json();
        $imageUrl = $data['data'][0]['url'] ?? null;

        if (!$imageUrl) {
            throw new Exception('9Router FLUX returned no image URL.');
        }

        $imageResponse = Http::timeout(60)->get($imageUrl);
        if ($imageResponse->failed()) {
            throw new Exception('Failed to download image from FLUX URL.');
        }

        $localPath = "generated/flux_" . uniqid() . ".jpg";
        Storage::disk('public')->put($localPath, $imageResponse->body());

        Log::info('9Router FLUX: image saved', ['path' => $localPath, 'size' => strlen($imageResponse->body())]);

        return [
            'url' => Storage::disk('public')->url($localPath),
            'raw_response' => $data,
            'provider' => '9router',
            'model' => $model,
        ];
    }

    protected function generateWithOpenAi(string $prompt, ?string $modelOverride = null): array
    {
        $apiKey = $this->providerCfg('openai', 'api_key');
        $model = $modelOverride ?: $this->providerCfg('openai', 'model');

        if (!$apiKey) {
            throw new Exception("OpenAI API key is missing.");
        }

        $response = Http::withToken($apiKey)
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'response_format' => 'url',
            ]);

        if ($response->failed()) {
            Log::error('OpenAI Image Gen Failed', ['response' => $response->json()]);
            throw new Exception('Failed to generate image with OpenAI: ' . $response->body());
        }

        $data = $response->json();

        return [
            'url' => $data['data'][0]['url'] ?? null,
            'raw_response' => $data,
            'provider' => 'openai',
            'model' => $model,
        ];
    }

    protected function generateWithStability(string $prompt, ?string $modelOverride = null): array
    {
        $apiKey = $this->providerCfg('stability', 'api_key');
        $model = $modelOverride ?: $this->providerCfg('stability', 'model');

        if (!$apiKey) {
            throw new Exception("Stability AI API key is missing.");
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("https://api.stability.ai/v1/generation/{$model}/text-to-image", [
            'text_prompts' => [
                ['text' => $prompt, 'weight' => 1],
            ],
            'cfg_scale' => 7,
            'height' => 1024,
            'width' => 1024,
            'samples' => 1,
            'steps' => 30,
        ]);

        if ($response->failed()) {
            Log::error('Stability AI Image Gen Failed', ['response' => $response->json()]);
            throw new Exception('Failed to generate image with Stability AI: ' . $response->body());
        }

        $data = $response->json();
        $artifact = $data['artifacts'][0] ?? null;

        if (!$artifact || !isset($artifact['base64'])) {
            throw new Exception('Stability AI returned no image data.');
        }

        $imageData = base64_decode($artifact['base64']);
        $filename = 'stability_' . uniqid() . '.png';
        $path = 'generated/' . $filename;
        Storage::disk('public')->put($path, $imageData);

        return [
            'url' => Storage::disk('public')->url($path),
            'raw_response' => $data,
            'provider' => 'stability',
            'model' => $model,
        ];
    }
}
