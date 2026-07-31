<?php

namespace Modules\SeoCluster\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClusterStructureService
{
    public array $tokenUsage = ['tokens_in' => 0, 'tokens_out' => 0];

    public function generateStructure(int $userId, string $topic, int $parentCount = 4, int $childCount = 4): array
    {
        $parentCount = max(1, min(10, $parentCount));
        $childCount = max(1, min(15, $childCount));

        $structure = $this->generateParentsWithChildren($topic, $parentCount, $childCount);

        $service = app(ClusterService::class);
        $clusters = [];

        foreach ($structure as $parent) {
            $keyword = trim($parent['keyword'] ?? '');
            if ($keyword === '') {
                continue;
            }

            $children = collect($parent['children'] ?? [])
                ->map(fn ($c) => trim($c))
                ->filter(fn ($c) => strlen($c) > 2)
                ->values()
                ->all();

            if (empty($children)) {
                $children = [$keyword];
            }

            $clusters[] = $service->createCluster(
                userId: $userId,
                name: $keyword,
                parentKeyword: $keyword,
                keywords: $children,
                description: "Cluster otomatis dari topik: {$topic}",
            );
        }

        return $clusters;
    }

    protected function generateParentsWithChildren(string $topic, int $parentCount, int $childCount): array
    {
        $ai = Setting::aiConfig();
        $url = $ai['url'];
        $apiKey = $ai['api_key'];
        $model = $ai['chat_model'];

        if (!$url) {
            throw new Exception('AI URL is not configured.');
        }

        $systemPrompt = <<<PROMPT
Kamu adalah ahli SEO content strategist bahasa Indonesia.

Tugas: dari sebuah topik utama, buatlah struktur keyword cluster sebagai berikut:
1. Buat tepat {$parentCount} parent keyword — subtopik utama yang berbeda, relevan dengan topik, dan saling melengkapi.
2. Untuk SETIAP parent keyword, buat tepat {$childCount} child keyword dalam bahasa Indonesia.
   - Child keyword berbentuk frasa long-tail spesifik yang bisa dijadikan judul artikel.
   - Setiap child keyword mengangkat aspek yang berbeda (cara, panduan, strategi, tips, kesalahan umum, dll).
   - Buat natural, tanpa terdengar kaku atau berulang.

Contoh topik "Optimasi On-Page SEO" → child keyword:
- "Cara optimasi meta description untuk meningkatkan CTR di Google"
- "Panduan struktur heading H1 hingga H6 untuk SEO on-page"
- "Strategi internal linking untuk meningkatkan otoritas halaman"
- "Tips optimasi URL structure yang SEO friendly dengan kata kunci target"

Return HANYA JSON valid tanpa markdown dan tanpa komentar:
{
  "parents": [
    {"keyword": "Parent 1", "children": ["child 1", "child 2", "..." ]},
    {"keyword": "Parent 2", "children": ["child 1", "child 2", "..."]}
  ]
}
PROMPT;

        $userPrompt = "Topik utama: {$topic}\nParent count: {$parentCount}\nChild per parent: {$childCount}";

        $raw = $this->callAI($systemPrompt, $userPrompt, $url, $apiKey, $model);
        $parsed = $this->parseJson($raw);

        $parents = $parsed['parents'] ?? [];
        if (empty($parents)) {
            throw new Exception('AI tidak mengembalikan struktur cluster yang valid.');
        }

        $result = [];
        foreach ($parents as $parent) {
            $result[] = [
                'keyword' => $parent['keyword'] ?? '',
                'children' => $parent['children'] ?? [],
            ];
        }

        return $result;
    }

    protected function callAI(string $systemPrompt, string $userPrompt, string $url, string $apiKey, string $model): string
    {
        $endpoint = str_ends_with(rtrim($url, '/'), '/v1')
            ? rtrim($url, '/') . '/chat/completions'
            : rtrim($url, '/') . '/v1/chat/completions';

        $maxAttempts = 3;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(180)
                    ->connectTimeout(30)
                    ->withHeaders([
                        'Authorization' => $apiKey ? "Bearer {$apiKey}" : '',
                        'Content-Type' => 'application/json',
                    ])->post($endpoint, [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 8192,
                        'stream' => false,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $usage = $data['usage'] ?? [];
                    $this->tokenUsage['tokens_in'] += $usage['prompt_tokens'] ?? 0;
                    $this->tokenUsage['tokens_out'] += $usage['completion_tokens'] ?? 0;

                    return $data['choices'][0]['message']['content'] ?? '';
                }

                $lastError = new Exception('AI HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 300));
            } catch (Exception $e) {
                $lastError = $e;
            }

            if ($attempt < $maxAttempts) {
                Log::warning('ClusterStructure: retry callAI', ['attempt' => $attempt, 'error' => $lastError->getMessage()]);
                sleep(3);
            }
        }

        throw $lastError ?? new Exception('Gagal terhubung ke AI.');
    }

    protected function parseJson(string $raw): ?array
    {
        $cleaned = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)));
        $decoded = json_decode($cleaned, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        preg_match('/\{(?:[^{}]|(?R))*\}/s', $cleaned, $matches);
        if (!empty($matches[0])) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
