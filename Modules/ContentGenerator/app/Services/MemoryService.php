<?php

namespace Modules\ContentGenerator\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Models\GenerationMemory;

class MemoryService
{
    protected function cfg(string $key, mixed $default = null): mixed
    {
        $db = Setting::getValue("content-generator.{$key}");
        if ($db !== null) {
            return $db;
        }
        return config("content-generator.{$key}", $default);
    }

    public function storeFromGeneration(ContentGeneration $generation): ?GenerationMemory
    {
        try {
            $text = $generation->phase_3_content ?: $generation->phase_1_content;
            $plain = strip_tags($text);
            $summary = mb_substr($plain, 0, 500);

            $embedding = $this->getEmbedding($generation->target_keyword . ': ' . $summary);

            return GenerationMemory::create([
                'user_id' => $generation->user_id,
                'content_generation_id' => $generation->id,
                'keyword' => $generation->target_keyword,
                'locale' => $generation->locale ?? 'id',
                'tone' => $generation->tone,
                'lsi_keywords' => $generation->lsi_keywords,
                'entities' => $generation->entities,
                'summary' => $summary,
                'embedding' => $embedding ? json_encode($embedding) : null,
            ]);
        } catch (Exception $e) {
            Log::warning('MemoryService: failed to store memory', [
                'generation_id' => $generation->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function retrieveSimilar(string $keyword, int $limit = 3, ?int $userId = null): array
    {
        $query = GenerationMemory::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $memories = $query->orderBy('created_at', 'desc')->get();

        $scored = [];
        foreach ($memories as $memory) {
            $score = $this->calculateSimilarity($keyword, $memory);

            if ($memory->is_reference) {
                $score = min($score + 0.4, 1.0);
            }
            if ($memory->quality_score) {
                $score = min($score + ($memory->quality_score - 3) * 0.1, 1.0);
            }

            if ($score > 0.1) {
                $scored[] = [
                    'memory' => $memory,
                    'score' => $score,
                ];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($item) => $item['memory'], array_slice($scored, 0, $limit));
    }

    public function buildMemoryPrompt(array $memories): string
    {
        if (empty($memories)) {
            return '';
        }

        $refs = array_filter($memories, fn($m) => $m->is_reference);
        $label = count($refs) > 0
            ? 'KONTEN REFERENSI (konten terbaik Anda yang sudah ditandai sebagai referensi — tiru gaya, kualitas, dan strukturnya):'
            : 'KONTEN TERKAIT YANG PERNAH ANDA BUAT SEBELUMNYA (jadikan referensi gaya, hindari duplikasi):';

        $text = "\n\n---\n{$label}\n";
        foreach ($memories as $i => $memory) {
            $badge = $memory->is_reference ? ' ⭐' : '';
            $text .= "\n### Memori #" . ($i + 1) . ": {$memory->keyword}{$badge}\n";
            if ($memory->summary) {
                $text .= "Ringkasan: {$memory->summary}\n";
            }
            if ($memory->is_reference && $memory->feedback) {
                $text .= "Catatan kualitas: {$memory->feedback}\n";
            }
        }
        $text .= "\nGunakan memori di atas sebagai referensi gaya penulisan yang sudah pernah Anda buat sebelumnya. JANGAN menyalin konten lama, tetapi jaga konsistensi kualitas.\n---\n";

        return $text;
    }

    private function calculateSimilarity(string $keyword, GenerationMemory $memory): float
    {
        if ($memory->embedding) {
            $emb = $this->getEmbedding($keyword);
            if ($emb) {
                $stored = json_decode($memory->embedding, true);
                if ($stored && count($stored) === count($emb)) {
                    return $this->cosineSimilarity($emb, $stored);
                }
            }
        }

        $kw = mb_strtolower($keyword);
        $memKw = mb_strtolower($memory->keyword);

        if ($kw === $memKw) {
            return 1.0;
        }

        $kwWords = explode(' ', $kw);
        $memWords = explode(' ', $memKw);

        $intersection = array_intersect($kwWords, $memWords);
        $union = array_unique(array_merge($kwWords, $memWords));

        if (empty($union)) {
            return 0;
        }

        $wordScore = count($intersection) / count($union);

        $lsi = $memory->lsi_keywords ?? [];
        $lsiText = '';
        foreach ($lsi as $lsiItem) {
            $lsiText .= ' ' . ($lsiItem['keyword'] ?? $lsiItem);
        }
        $lsiWords = explode(' ', mb_strtolower(trim($lsiText)));
        $lsiIntersection = array_intersect($kwWords, $lsiWords);
        $lsiScore = count($lsiIntersection) > 0 ? 0.3 : 0;

        return min($wordScore + $lsiScore, 1.0);
    }

    private function getEmbedding(string $text): ?array
    {
        try {
            $url = Setting::getValue('ai.9router.url', config('content-generator.providers.9router.url'));
            $apiKey = Setting::getValue('ai.9router.api_key', config('content-generator.providers.9router.api_key'));

            if (!$url || !$apiKey) {
                return null;
            }

            $response = Http::timeout(15)->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->post("{$url}/v1/embeddings", [
                'model' => 'openai/text-embedding-3-small',
                'input' => $text,
            ]);

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();
            return $data['data'][0]['embedding'] ?? null;
        } catch (Exception $e) {
            Log::warning('MemoryService: embedding failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0;
        $normA = 0;
        $normB = 0;

        foreach ($a as $i => $value) {
            $dot += $value * ($b[$i] ?? 0);
            $normA += $value * $value;
            $normB += ($b[$i] ?? 0) * ($b[$i] ?? 0);
        }

        $denom = sqrt($normA) * sqrt($normB);
        return $denom > 0 ? $dot / $denom : 0;
    }

    public function pruneOldMemories(int $keepPerUser = 50): int
    {
        $pruned = 0;
        $userIds = GenerationMemory::select('user_id')->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            $ids = GenerationMemory::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->pluck('id');

            if ($ids->count() <= $keepPerUser) {
                continue;
            }

            $toDelete = $ids->slice($keepPerUser)->values();
            GenerationMemory::whereIn('id', $toDelete)->delete();
            $pruned += $toDelete->count();
        }

        return $pruned;
    }
}
