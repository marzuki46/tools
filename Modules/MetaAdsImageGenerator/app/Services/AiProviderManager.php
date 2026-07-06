<?php

namespace Modules\MetaAdsImageGenerator\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiProviderManager
{
    public function generateImage(string $prompt, ?string $provider = null): array
    {
        $provider = $provider ?? config('meta-ads-image-generator.default_provider');

        return match ($provider) {
            'openai' => $this->generateWithOpenAi($prompt),
            'stability' => $this->generateWithStability($prompt),
            '9router' => $this->generateWith9Router($prompt),
            default => throw new Exception("AI Provider [{$provider}] not supported."),
        };
    }

    protected function generateWithOpenAi(string $prompt): array
    {
        $apiKey = config('meta-ads-image-generator.providers.openai.api_key');
        $model = config('meta-ads-image-generator.providers.openai.model');

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

    protected function generateWithStability(string $prompt): array
    {
        // Placeholder for Stability AI implementation
        throw new Exception("Stability AI integration not yet implemented.");
    }

    protected function generateWith9Router(string $prompt): array
    {
        $url = config('meta-ads-image-generator.providers.9router.url');
        $apiKey = config('meta-ads-image-generator.providers.9router.api_key');
        $model = config('meta-ads-image-generator.providers.9router.model');

        if (!$url) {
            throw new Exception("9Router URL is missing.");
        }

        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ]);

        if ($apiKey) {
            $request->withToken($apiKey);
        }

        $response = $request->post($url . '/v1/images/generations', [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'response_format' => 'url',
        ]);

        if ($response->failed()) {
            Log::error('9Router Image Gen Failed', ['response' => $response->json()]);
            throw new Exception('Failed to generate image with 9Router: ' . $response->body());
        }

        $data = $response->json();

        return [
            'url' => $data['data'][0]['url'] ?? ($data['data'][0]['b64_json'] ?? null),
            'raw_response' => $data,
            'provider' => '9router',
            'model' => $model,
        ];
    }
}
