<?php

namespace Modules\GeoContent\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\GeoContent\Models\GeoProject;

class CriticalQuestionService
{
    protected function cfg(string $key, mixed $default = null): mixed
    {
        $db = Setting::getValue("geo-content.{$key}");
        if ($db !== null) return $db;
        return config("geo-content.{$key}", $default);
    }

    public function generate(GeoProject $project): array
    {
        $keyword = $project->keyword_utama;
        $locale = $project->locale ?? 'id';

        // Ambil LSI & entities dari riset keyword
        $lsiText = '';
        $entityText = '';
        if ($project->keywordResearch) {
            foreach ($project->keywordResearch->lsi_keywords ?? [] as $lsi) {
                $kw = $lsi['keyword'] ?? $lsi;
                $lsiText .= "- {$kw}\n";
            }
            foreach ($project->keywordResearch->entities ?? [] as $entity) {
                $name = $entity['name'] ?? $entity;
                $type = $entity['type'] ?? '';
                $entityText .= "- {$name}" . ($type ? " ({$type})" : '') . "\n";
            }
        }

        $factService = app(CompetitorFactService::class);
        $factsSummary = mb_substr($factService->getSynthesis($project), 0, 4000);

        $prompt = <<<PROMPT
Anda adalah editor konten senior dan pakar SEO. Topik: "{$keyword}".

LSI Keywords:
{$lsiText}
Entities:
{$entityText}

Ringkasan fakta dari kompetitor (sudah disanitasi, tanpa brand):
{$factsSummary}

Buat 5-8 pertanyaan kritis yang HARUS dijawab artikel tentang "{$keyword}" agar unggul dari kompetitor.
Pertanyaan harus:
- Menguji kelengkapan fakta dan E-E-A-T
- Mencakup intent yang belum terjawab kompetitor
- Relevan dengan LSI/entities di atas
- Membantu pembaca mendapat pemahaman lengkap

Return ONLY valid JSON array:
[
  {"question": "Pertanyaan 1?"},
  {"question": "Pertanyaan 2?"}
]
PROMPT;

        $raw = $this->callAI($prompt);
        $questions = $this->parseQuestions($raw);

        if (empty($questions)) {
            return $this->fallback($keyword);
        }

        // Simpan ke DB
        \Modules\GeoContent\Models\GeoCriticalFinding::where('geo_project_id', $project->id)->delete();
        $rank = 1;
        foreach ($questions as $q) {
            \Modules\GeoContent\Models\GeoCriticalFinding::create([
                'geo_project_id' => $project->id,
                'question' => $q['question'] ?? $q,
                'rank' => $rank++,
            ]);
        }

        return $questions;
    }

    private function callAI(string $prompt): string
    {
        $ai = Setting::aiConfig();
        $url = $ai['url'];
        $apiKey = $ai['api_key'];
        $model = $ai['chat_model'];

        if (!$url) throw new \Exception('AI URL belum dikonfigurasi.');

        $delay = (int) $this->cfg('ai.request_delay', 2);
        if ($delay > 0) sleep($delay);

        $endpoint = str_ends_with(rtrim($url, '/'), '/v1') ? rtrim($url, '/') . '/chat/completions' : rtrim($url, '/') . '/v1/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Anda adalah editor SEO senior. Jawab HANYA dengan JSON valid.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 4096,
            'stream' => false,
        ];

        $response = Http::timeout(120)->withHeaders([
            'Authorization' => $apiKey ? "Bearer {$apiKey}" : '',
            'Content-Type' => 'application/json',
        ])->post($endpoint, $payload);

        if (!$response->successful()) {
            $body = $response->body();
            if (str_contains($body, 'MONTHLY_REQUEST_COUNT') || $response->status() === 402) {
                throw new \Exception('AI quota habis (MONTHLY_REQUEST_COUNT) — ganti model di Settings/AI atau tunggu reset. ' . substr($body, 0, 200));
            }
            throw new \Exception('AI HTTP ' . $response->status() . ': ' . substr($body, 0, 300));
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function parseQuestions(string $raw): array
    {
        $clean = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)));
        $decoded = json_decode($clean, true);
        if (is_array($decoded) && isset($decoded[0]['question'])) return $decoded;
        if (is_array($decoded)) {
            // Try extract question keys
            $out = [];
            foreach ($decoded as $item) {
                if (is_array($item) && isset($item['question'])) $out[] = $item;
                elseif (is_string($item)) $out[] = ['question' => $item];
            }
            if (!empty($out)) return $out;
        }
        preg_match_all('/"question"\s*:\s*"([^"]+)"/', $clean, $m);
        if (!empty($m[1])) return array_map(fn ($q) => ['question' => $q], $m[1]);
        return [];
    }

    private function fallback(string $keyword): array
    {
        return [
            ['question' => "Apa itu {$keyword} secara mendalam?"],
            ['question' => "Mengapa {$keyword} penting bagi pembaca?"],
            ['question' => "Bagaimana cara memulai {$keyword} untuk pemula?"],
            ['question' => "Apa kesalahan umum terkait {$keyword}?"],
            ['question' => "Bagaimana masa depan {$keyword}?"],
        ];
    }
}
