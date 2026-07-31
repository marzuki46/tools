<?php

namespace Modules\AgentConnector\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\AgentConnector\Models\AgentMemory;

class RagService
{
    public function embedText(string $text): ?array
    {
        $cacheKey = 'rag_embed_' . md5($text);
        $cached = cache()->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $embedding = $this->fetchEmbedding($text);

        if ($embedding) {
            cache()->put($cacheKey, $embedding, now()->addMinutes(30));
        }

        return $embedding;
    }

    protected function fetchEmbedding(string $text): ?array
    {
        $url = Setting::getValue('ai.9router.url', config('agent-connector.ai.url'));
        $apiKey = Setting::getValue('ai.9router.api_key', config('agent-connector.ai.api_key'));
        $model = Setting::getValue('ai.9router.embedding_model', config('agent-connector.ai.embedding_model', 'gemini/gemini-embedding-001'));

        if (!$url) {
            return null;
        }

        $endpoint = str_ends_with(rtrim($url, '/'), '/v1')
            ? rtrim($url, '/') . '/embeddings'
            : rtrim($url, '/') . '/v1/embeddings';

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
                        'input' => mb_substr($text, 0, 8000),
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $embedding = $data['data'][0]['embedding'] ?? null;

                    if (is_array($embedding)) {
                        return array_map('floatval', $embedding);
                    }

                    $lastError = new Exception('Embedding kosong dari API');
                } else {
                    $lastError = new Exception('9Router HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 300));
                }
            } catch (Exception $e) {
                $lastError = $e;
            }

            if ($attempt < $maxAttempts) {
                Log::warning('RagService: retry embedding', ['attempt' => $attempt, 'error' => $lastError->getMessage()]);
                sleep(3 * $attempt);
            }
        }

        Log::warning('RagService: embedding failed', ['error' => $lastError?->getMessage()]);
        return null;
    }

    public function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $count = min(count($a), count($b));

        for ($i = 0; $i < $count; $i++) {
            $x = (float) ($a[$i] ?? 0);
            $y = (float) ($b[$i] ?? 0);
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        $denom = sqrt($normA) * sqrt($normB);

        return $denom > 0 ? $dot / $denom : 0.0;
    }

    public function storeEmbedding(int $userId, string $type, string $key, string $content, ?array $metadata = null): AgentMemory
    {
        $embedding = $this->embedText($content);

        $memory = AgentMemory::remember($userId, $type, $key, $content, $metadata);

        if ($embedding) {
            $memory->update(['embedding' => $embedding]);
        }

        return $memory;
    }

    public function semanticSearch(int $userId, string $query, int $limit = 5, float $minScore = 0.6): array
    {
        $queryEmbedding = $this->embedText($query);

        if (!$queryEmbedding) {
            return [];
        }

        $candidates = AgentMemory::where('user_id', $userId)
            ->whereNotNull('embedding')
            ->orderBy('updated_at', 'desc')
            ->take(200)
            ->get();

        $results = [];

        foreach ($candidates as $memory) {
            $memoryEmbedding = $memory->embedding;

            if (!$memoryEmbedding) {
                continue;
            }

            $score = $this->cosineSimilarity($queryEmbedding, $memoryEmbedding);

            if ($score >= $minScore) {
                $results[] = [
                    'id' => $memory->id,
                    'type' => $memory->type,
                    'key' => $memory->key,
                    'content' => Str::limit($memory->content, 500),
                    'metadata' => $memory->metadata,
                    'score' => round($score, 4),
                ];
            }
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    public function hybridSearch(int $userId, string $query, int $limit = 5, float $minScore = 0.6): array
    {
        $semantic = $this->semanticSearch($userId, $query, $limit * 2, $minScore);

        if (!empty($semantic)) {
            return array_slice($semantic, 0, $limit);
        }

        $keywordMemories = AgentMemory::where('user_id', $userId)
            ->where(function ($q) use ($query) {
                $words = array_filter(explode(' ', $query));
                foreach ($words as $word) {
                    if (mb_strlen($word) > 3) {
                        $q->orWhere('content', 'like', "%{$word}%");
                    }
                }
            })
            ->orderBy('updated_at', 'desc')
            ->take($limit * 2)
            ->get()
            ->unique('key')
            ->take($limit)
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'key' => $m->key,
                'content' => Str::limit($m->content, 500),
                'metadata' => $m->metadata,
                'score' => 0,
            ])
            ->values()
            ->toArray();

        return $keywordMemories;
    }
}
