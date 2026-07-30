<?php

namespace App\Services\SeoAgent;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleTrendsService
{
    public function analyze(string $keyword, string $locale = 'id'): array
    {
        $url = Setting::getValue('ai.9router.url', config('keyword-research.providers.9router.url'));
        $apiKey = Setting::getValue('ai.9router.api_key', config('keyword-research.providers.9router.api_key'));
        $model = Setting::getValue('ai.9router.chat_model', config('keyword-research.providers.9router.model', 'openai/gpt-4o'));

        if (!$url) {
            return $this->fallbackTrends($keyword);
        }

        $prompt = <<<PROMPT
Anda adalah analis Google Trends. Analisis keyword berikut dan berikan data tren terkini.

Keyword: {$keyword}

Beri analisis yang mencakup:
1. **Tren saat ini**: Apakah keyword ini sedang naik, stabil, atau turun?
2. **Minat regional**: Di wilayah mana keyword ini populer?
3. **Topik terkait**: Topik apa yang sering muncul bersamaan?
4. **Pertanyaan terkait**: Pertanyaan apa yang sering diajukan orang tentang keyword ini?
5. **Prediksi**: Kecenderungan ke depan?

Keluarkan dalam format JSON berikut (HANYA JSON, tanpa markdown):
{
  "trend_direction": "naik|stabil|turun",
  "trend_score": 0-100,
  "summary": "Ringkasan 2-3 kalimat",
  "related_topics": ["Topik 1", "Topik 2", "Topik 3"],
  "related_questions": ["Pertanyaan 1?", "Pertanyaan 2?"],
  "regions": ["Wilayah 1", "Wilayah 2"],
  "prediction": "Prediksi singkat"
}
PROMPT;

        try {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => $apiKey ? "Bearer {$apiKey}" : '',
                'Content-Type' => 'application/json',
            ])->post("{$url}/v1/chat/completions", [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Anda adalah analis data Google Trends. Jawab hanya dengan JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'stream' => false,
            ]);

            if ($response->failed()) {
                throw new Exception('API request failed: ' . $response->body());
            }

            $data = $response->json();
            $raw = $data['choices'][0]['message']['content'] ?? '';
            $raw = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw));
            $parsed = json_decode($raw, true);

            if (!is_array($parsed)) {
                throw new Exception('Failed to parse AI response');
            }

            return $parsed;
        } catch (Exception $e) {
            Log::warning('GoogleTrendsService: AI analysis failed', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);
            return $this->fallbackTrends($keyword);
        }
    }

    protected function fallbackTrends(string $keyword): array
    {
        return [
            'trend_direction' => 'stabil',
            'trend_score' => 50,
            'summary' => "Data tren untuk '{$keyword}' tidak tersedia saat ini. Silakan cek langsung di https://trends.google.com.",
            'related_topics' => [],
            'related_questions' => [],
            'regions' => [],
            'prediction' => 'Tidak dapat diprediksi tanpa data aktual.',
        ];
    }
}
