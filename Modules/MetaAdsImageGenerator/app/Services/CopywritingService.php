<?php

namespace Modules\MetaAdsImageGenerator\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CopywritingService
{
    protected function cfg(string $key, mixed $default = null): mixed
    {
        $db = Setting::getValue($key);
        if ($db !== null) {
            return $db;
        }
        return config("meta-ads-image-generator.{$key}", $default);
    }

    public function generateCopy(array $input, ?string $modelOverride = null): array
    {
        $url = Setting::getValue('ai.9router.url', config('meta-ads-image-generator.providers.9router.url'));
        $apiKey = Setting::getValue('ai.9router.api_key', config('meta-ads-image-generator.providers.9router.api_key'));
        $model = $modelOverride ?: Setting::getValue('ai.9router.chat_model', config('meta-ads-image-generator.providers.9router.chat_model', 'openai/gpt-4o'));

        if (!$url) {
            throw new Exception('9Router URL is not configured for copywriting.');
        }

        $systemPrompt = <<<PROMPT
You are an expert Facebook & Instagram ad copywriter. Given a product name and optional details,
generate 4 DIFFERENT compelling ad copy variations in Indonesian language.

Return ONLY valid JSON array (no markdown, no code fences) with exactly 4 items:

[
  {
    "headline": "short attention-grabbing headline max 40 chars",
    "sub_headline": "supporting line max 80 chars",
    "cta": "call to action in Indonesian",
    "description": "brief ad description for AI image prompt max 200 chars"
  },
  ... (4 total variations)
]

Guidelines:
- Each variation must be DIFFERENT in style/tone
- Headline must be short, powerful, in Indonesian
- CTA options: Beli Sekarang, Daftar Gratis, Hubungi Kami, Pelajari Lebih, Pesan Sekarang, Coba Gratis
- Description should describe the visual scene for image generation
- Match the tone to the vibe/mood if provided
- If target audience is specified, tailor language to them
- Variation 1: Professional/formal
- Variation 2: Casual/fun
- Variation 3: Urgency/FOMO
- Variation 4: Benefit-focused
PROMPT;

        $userPrompt = "Product: {$input['product_name']}";
        if (!empty($input['headline_hint'])) {
            $userPrompt .= "\nHeadline hint: {$input['headline_hint']}";
        }
        if (!empty($input['vibe'])) {
            $userPrompt .= "\nVibe/Mood: {$input['vibe']}";
        }
        if (!empty($input['target_audience'])) {
            $userPrompt .= "\nTarget audience: {$input['target_audience']}";
        }
        if (!empty($input['notes'])) {
            $userPrompt .= "\nAdditional notes: {$input['notes']}";
        }

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => $apiKey ? "Bearer {$apiKey}" : '',
            'Content-Type' => 'application/json',
        ])->post("{$url}/v1/chat/completions", [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.9,
            'max_tokens' => 1500,
        ]);

        if ($response->failed()) {
            Log::error('Copywriting 9Router Chat Failed', ['response' => $response->body()]);
            throw new Exception('Failed to generate copy: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content));

        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            Log::warning('Copywriting: failed to parse AI response', ['raw' => $content]);
            return $this->fallbackCopies($input['product_name']);
        }

        // If it's a single object (old format), wrap in array
        if (isset($parsed['headline'])) {
            $parsed = [$parsed];
        }

        // Ensure we have exactly 4 variations
        $variations = [];
        for ($i = 0; $i < 4; $i++) {
            if (isset($parsed[$i]) && is_array($parsed[$i])) {
                $variations[] = [
                    'headline' => $parsed[$i]['headline'] ?? '',
                    'sub_headline' => $parsed[$i]['sub_headline'] ?? '',
                    'cta' => $parsed[$i]['cta'] ?? '',
                    'description' => $parsed[$i]['description'] ?? '',
                ];
            } else {
                $variations[] = $this->fallbackCopies($input['product_name'])[$i] ?? $this->fallbackCopies($input['product_name'])[0];
            }
        }

        return $variations;
    }

    private function fallbackCopies(string $productName): array
    {
        return [
            [
                'headline' => mb_substr("Promo {$productName} Spesial!", 0, 40),
                'sub_headline' => mb_substr("Dapatkan {$productName} terbaik dengan harga spesial hari ini", 0, 80),
                'cta' => 'Beli Sekarang',
                'description' => "Professional product photo of {$productName} with dramatic lighting, premium background, high quality, commercial photography style",
            ],
            [
                'headline' => mb_substr("{$productName} Keren Banget!", 0, 40),
                'sub_headline' => mb_substr("Wajib punya! {$productName} bikin hidup lebih mudah", 0, 80),
                'cta' => 'Coba Gratis',
                'description' => "Lifestyle photo of {$productName} in modern setting, bright colors, happy mood, social media style",
            ],
            [
                'headline' => mb_substr("Jangan Sampai Kehabisan!", 0, 40),
                'sub_headline' => mb_substr("Stok terbatas! {$productName} lagi promo besar-besaran", 0, 80),
                'cta' => 'Pesan Sekarang',
                'description' => "Limited edition shot of {$productName} with red accent lighting, urgency feel, premium commercial style",
            ],
            [
                'headline' => mb_substr("Solusi Terbaik: {$productName}", 0, 40),
                'sub_headline' => mb_substr("Hemat waktu & tenaga dengan {$productName}", 0, 80),
                'cta' => 'Pelajari Lebih',
                'description' => "Clean minimal product shot of {$productName} on white background, soft shadows, professional catalog style",
            ],
        ];
    }
}
