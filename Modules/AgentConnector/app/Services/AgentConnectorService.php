<?php

namespace Modules\AgentConnector\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\AgentConnector\Models\AgentMemory;
use Modules\AgentConnector\Models\AgentSession;
use Modules\AgentConnector\Models\AgentToolRegistry;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\KeywordResearch\Models\KeywordResearch;

class AgentConnectorService
{
    public array $tokenUsage = ['tokens_in' => 0, 'tokens_out' => 0];

    public function __construct(
        protected RagService $rag,
    ) {}

    public function processInput(int $userId, string $input, string $source = 'telegram', ?string $sessionId = null, ?callable $onProgress = null): array
    {
        $this->ensureToolRegistrySynced();
        $sessionId ??= (string) $userId;
        $session = $this->getOrCreateSession($userId, $sessionId);

        $this->reportProgress($onProgress, 'Mencari konteks & memory...');
        $memories = $this->retrieveMemories($userId, $input);

        $this->reportProgress($onProgress, 'Menganalisis pesan...');
        $intentResult = $this->analyzeIntent($input, $memories, $session);

        $session->update([
            'intent' => $intentResult['intent'],
            'active_tool' => $intentResult['tool'] ?? null,
            'context' => [
                'last_input' => $input,
                'last_intent' => $intentResult['intent'],
                'last_tool' => $intentResult['tool'] ?? null,
                'params' => $intentResult['params'] ?? [],
            ],
        ]);

        $actions = [];

        if (isset($intentResult['tool']) && $intentResult['tool'] !== 'none') {
            $this->reportProgress($onProgress, 'Menjalankan tool: ' . $intentResult['tool'] . '...');

            $toolResult = $this->executeTool(
                $intentResult['tool'],
                $intentResult['action'] ?? '',
                $intentResult['params'] ?? [],
                $userId,
            );
            $actions[] = $toolResult;

            $this->saveMemory($userId, 'tool_result', "tool.{$intentResult['tool']}." . Str::uuid(), json_encode($toolResult), [
                'tool' => $intentResult['tool'],
                'intent' => $intentResult['intent'],
            ]);

            $this->reportProgress($onProgress, 'Hasil tool ' . $intentResult['tool'] . ' siap.');
        }

        $this->saveMemory($userId, 'conversation', "conv.{$sessionId}." . now()->timestamp, $input, [
            'intent' => $intentResult['intent'],
            'source' => $source,
        ]);

        $this->reportProgress($onProgress, 'Menyusun jawaban...');
        $response = $this->buildResponse($intentResult, $actions, $memories, $input);

        $this->saveMemory($userId, 'conversation', "conv.{$sessionId}." . now()->timestamp . '.asst', $response, [
            'intent' => $intentResult['intent'],
            'source' => $source,
            'role' => 'assistant',
        ]);

        $this->reportProgress($onProgress, 'Selesai');

        return [
            'response' => $response,
            'intent' => $intentResult['intent'],
            'tool_called' => $intentResult['tool'] ?? null,
            'actions' => $actions,
        ];
    }

    protected function reportProgress(?callable $callback, string $stage): void
    {
        if ($callback) {
            try {
                $callback($stage);
            } catch (Exception $e) {
                Log::warning('AgentConnector: progress callback gagal', ['error' => $e->getMessage()]);
            }
        }
    }

    public function analyzeIntent(string $input, array $memories, AgentSession $session): array
    {
        $rule = $this->ruleBasedIntent($input);

        if (($rule['confidence'] ?? 0) >= 0.85) {
            return $rule;
        }

        $tools = AgentToolRegistry::active()->get();
        $toolsDescription = $tools->map(fn ($t) => "- {$t['name']} ({$t['slug']}): {$t['description']}"
            . $this->formatCapabilities($t->capabilities))->implode("\n");

        $memoryText = collect($memories)->map(fn ($m) => "[{$m['type']}] {$m['content']}")->implode("\n");

        $sessionContext = $session->context ? json_encode($session->context) : 'Tidak ada sesi sebelumnya';

        $productKnowledge = app(ProductKnowledgeService::class)->markdown();

        $systemPrompt = <<<PROMPT
Anda adalah Agent Connector, asisten AI yang menjadi otak sistem SEO tools.
Tugas Anda: pahami input user, baca konteks dari memory, dan pilih tool yang tepat.

{$productKnowledge}

TOOL YANG TERSEDIA:
{$toolsDescription}

KONTEKS MEMORY USER:
{$memoryText}

SESI SEBELUMNYA:
{$sessionContext}

TUGAS:
1. Analisis intent user dari input
2. Jika input bersifat percakapan umum (sapaan, thanks, dll), pilih tool "none"
3. Jika input meminta sesuatu yang bisa dikerjakan tool, pilih tool + action yang tepat
4. Ekstrak parameter yang relevan dari input (keyword, id, topic, parent_count, child_count, dll)
5. Jika user menyebut "konten terakhir"/"artikel terakhir"/"konten saya", isi params generation_id dengan id generation terakhir user (kosongkan dulu, agent service yang mengisinya)

RESPONSE: Hanya return JSON tanpa markdown:
{
  "intent": "nama_intent",
  "tool": "slug_tool_atau_none",
  "action": "nama_action",
  "params": {},
  "confidence": 0.95,
  "reasoning": "Penjelasan singkat kenapa pilih tool ini"
}

INTENT LIST: create_cluster, list_clusters, cluster_status, start_cluster, stop_cluster,
add_keyword, research_keyword, generate_content, analyze_content, publish_content,
check_trend, help, general_chat, agent_status, cluster_add_keyword
PROMPT;

        $userPrompt = "Input user: {$input}";

        try {
            $raw = $this->callAI($systemPrompt, $userPrompt);
            $decoded = $this->parseJson($raw);
        } catch (Exception $e) {
            Log::warning('AgentConnector: intent AI gagal, pakai fallback rule', ['error' => $e->getMessage()]);
            $decoded = null;
        }

        if (!$decoded || !isset($decoded['intent'])) {
            return $this->ruleBasedIntent($input);
        }

        return $decoded;
    }

    public function ruleBasedIntent(string $input): array
    {
        $input = mb_strtolower(trim($input));

        if (preg_match('/(cara pakai|cara menggunakan|cara pemakaian|tutorial pakai|cara kerja)/i', $input) || preg_match('/\b(cara|bagaimana cara|gimana cara|gimana sih cara)\b.*\b(?:pakai|gunakan|tools|fitur|modul|sistem)\b/i', $input)) {
            return ['intent' => 'help', 'tool' => 'none', 'action' => '', 'params' => [], 'confidence' => 0.92, 'reasoning' => 'Rule fallback: cara penggunaan'];
        }

        if (preg_match('/(buat|tambah|create)\s+cluster|cluster\s+baru/i', $input)) {
            return ['intent' => 'create_cluster', 'tool' => 'keyword-clusters', 'action' => 'create', 'params' => $this->extractClusterParams($input), 'confidence' => 0.9, 'reasoning' => 'Rule fallback: membuat cluster'];
        }

        if (preg_match('/cluster\s+(saya|apa\s+saja|ku|list)/i', $input) || str_contains($input, 'daftar cluster')) {
            return ['intent' => 'list_clusters', 'tool' => 'keyword-clusters', 'action' => 'list', 'params' => [], 'confidence' => 0.9, 'reasoning' => 'Rule fallback: daftar cluster'];
        }

        if (preg_match('/progress|status\s+cluster|perkembangan/i', $input)) {
            return ['intent' => 'cluster_status', 'tool' => 'keyword-clusters', 'action' => 'status', 'params' => [], 'confidence' => 0.9, 'reasoning' => 'Rule fallback: status cluster'];
        }

        if (preg_match('/(mulai|aktifkan|start)\s+cluster/i', $input)) {
            return ['intent' => 'start_cluster', 'tool' => 'keyword-clusters', 'action' => 'start', 'params' => $this->extractClusterParams($input), 'confidence' => 0.9, 'reasoning' => 'Rule fallback: mulai cluster'];
        }

        if (preg_match('/(berhenti|pause|stop)\s+cluster/i', $input)) {
            return ['intent' => 'stop_cluster', 'tool' => 'keyword-clusters', 'action' => 'stop', 'params' => $this->extractClusterParams($input), 'confidence' => 0.9, 'reasoning' => 'Rule fallback: stop cluster'];
        }

        if (preg_match('/(tambah\s+keyword|add\s+keyword|keyword\s+ke\s+cluster)/i', $input)) {
            return ['intent' => 'cluster_add_keyword', 'tool' => 'keyword-clusters', 'action' => 'add_keyword', 'params' => $this->extractClusterParams($input), 'confidence' => 0.85, 'reasoning' => 'Rule fallback: tambah keyword'];
        }

        if (preg_match('/(riset|research)\s+keyword/i', $input)) {
            return ['intent' => 'research_keyword', 'tool' => 'keyword-research', 'action' => 'research', 'params' => $this->extractKeywordParams($input), 'confidence' => 0.9, 'reasoning' => 'Rule fallback: riset keyword'];
        }

        if (preg_match('/(generate|buat)\s+(konten|artikel|content)/i', $input)) {
            return ['intent' => 'generate_content', 'tool' => 'content-generator', 'action' => 'generate', 'params' => $this->extractKeywordParams($input), 'confidence' => 0.9, 'reasoning' => 'Rule fallback: generate konten'];
        }

        if (preg_match('/(analisa|analisis|analy[sz]e)\s+(konten|content|artikel)/i', $input)) {
            return ['intent' => 'analyze_content', 'tool' => 'content-analyzer', 'action' => 'analyze', 'params' => $this->extractKeywordParams($input), 'confidence' => 0.9, 'reasoning' => 'Rule fallback: analisa konten'];
        }

        if (preg_match('/(publish|posting|terbitkan)/i', $input)) {
            return ['intent' => 'publish_content', 'tool' => 'wordpress-publisher', 'action' => 'publish', 'params' => $this->extractKeywordParams($input), 'confidence' => 0.85, 'reasoning' => 'Rule fallback: publish konten'];
        }

        if (preg_match('/(bantuan|help|fitur)/i', $input)) {
            return ['intent' => 'help', 'tool' => 'none', 'action' => '', 'params' => [], 'confidence' => 0.95, 'reasoning' => 'Rule fallback: bantuan'];
        }

        if (preg_match('/(hai|halo|hello|pagi|siang|sore|malam|terima kasih|thanks|thank|makasih|makasi|mksh|mksd)/i', $input)) {
            return ['intent' => 'general_chat', 'tool' => 'none', 'action' => '', 'params' => [], 'confidence' => 0.9, 'reasoning' => 'Rule fallback: percakapan umum'];
        }

        return ['intent' => 'general_chat', 'tool' => 'none', 'action' => '', 'params' => [], 'confidence' => 0.5, 'reasoning' => 'Rule fallback: tidak terdeteksi'];
    }

    protected function extractKeywordParams(string $input): array
    {
        if (preg_match('/["\'«]([^"\'»]+)["\'»]/u', $input, $m)) {
            return ['keyword' => trim($m[1])];
        }

        if (preg_match('/(?:keyword|tentang|untuk|riset)\s+(.+)$/i', $input, $m)) {
            $keyword = trim(preg_replace('/\s*(dan|tolong|saya\s+ingin|bisa|mohon)\s*$/i', '', $m[1]));
            if (mb_strlen($keyword) > 2) {
                return ['keyword' => $keyword];
            }
        }

        return [];
    }

    protected function extractClusterParams(string $input): array
    {
        $params = [];

        if (preg_match('/cluster\s+#?(\d+)/i', $input, $m)) {
            $params['id'] = (int) $m[1];
        }

        if (preg_match('/["\'«]([^"\'»]+)["\'»]/u', $input, $m)) {
            $params['parent_keyword'] = trim($m[1]);
        } elseif (preg_match('/(?:keyword|tentang|untuk)\s+(.+)$/i', $input, $m)) {
            $params['parent_keyword'] = trim($m[1]);
        }

        if (preg_match('/(?:nama|name)\s+["\']?([^"\',]+)["\']?/i', $input, $m)) {
            $params['name'] = trim($m[1]);
        }

        if (preg_match('/(\d+)\s*(?:parent|sub.?topik|kategori)/i', $input, $m)) {
            $params['parent_count'] = (int) $m[1];
        }

        if (preg_match('/(\d+)\s*(?:child|keyword)\s*(?:per\s+parent)?/i', $input, $m)) {
            $params['child_count'] = (int) $m[1];
        }

        $topic = null;
        if (preg_match('/["\'«]([^"\'»]+)["\'»]/u', $input, $m)) {
            $topic = trim($m[1]);
        } elseif (preg_match('/(?:buat|tambah|create)\s+cluster(?:\s+baru)?\s+(?:dengan\s+)?(.+)$/i', $input, $m)) {
            $topic = trim($m[1]);
        }
        if ($topic) {
            $topic = preg_replace('/\s+(?:dengan|sebanyak)\s+\d+\s*(?:parent|child).*$/i', '', $topic);
            $topic = trim($topic);
            if (mb_strlen($topic) > 2 && !isset($params['parent_keyword'])) {
                $params['topic'] = $topic;
            }
        }

        return $params;
    }

    public function retrieveMemories(int $userId, string $input): array
    {
        $limit = config('agent-connector.memory.max_results', 5);
        $minScore = (float) config('agent-connector.memory.min_score', 0.6);

        $semanticMemories = $this->rag->semanticSearch($userId, $input, $limit * 2, $minScore);

        $recentMemories = AgentMemory::where('user_id', $userId)
            ->where('type', 'conversation')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        if (empty($semanticMemories)) {
            $keywordMemories = AgentMemory::where('user_id', $userId)
                ->where(function ($q) use ($input) {
                    $words = array_filter(explode(' ', $input));
                    foreach ($words as $word) {
                        if (strlen($word) > 3) {
                            $q->orWhere('content', 'like', "%{$word}%");
                        }
                    }
                })
                ->orderBy('updated_at', 'desc')
                ->take($limit * 2)
                ->get()
                ->unique('key')
                ->take($limit);
        } else {
            $keywordMemories = collect($semanticMemories)->map(fn ($m) => (object) $m);
        }

        $merged = collect($keywordMemories)
            ->merge($recentMemories)
            ->unique('id')
            ->take($limit)
            ->map(fn ($m) => [
                'id' => is_object($m) ? $m->id : $m['id'],
                'type' => is_object($m) ? $m->type : $m['type'],
                'key' => is_object($m) ? $m->key : $m['key'],
                'content' => is_object($m) ? Str::limit($m->content, 500) : $m['content'],
                'metadata' => is_object($m) ? $m->metadata : ($m['metadata'] ?? null),
            ])
            ->values()
            ->toArray();

        return $merged;
    }

    public function saveMemory(int $userId, string $type, string $key, string $content, ?array $metadata = null): AgentMemory
    {
        $embedding = $this->rag->embedText($content);

        $memory = AgentMemory::remember($userId, $type, $key, $content, $metadata);

        if ($embedding) {
            $memory->update(['embedding' => $embedding]);
        }

        return $memory;
    }

    public function executeTool(string $toolSlug, string $action, array $params, int $userId): array
    {
        $tool = AgentToolRegistry::where('slug', $toolSlug)->first();

        if (!$tool) {
            return ['tool' => $toolSlug, 'status' => 'error', 'message' => "Tool {$toolSlug} tidak dikenal."];
        }

        $start = microtime(true);

        try {
            $result = match ($toolSlug) {
                'keyword-clusters' => $this->executeKeywordCluster($action, $params, $userId),
                'keyword-research' => $this->executeKeywordResearch($action, $params, $userId),
                'content-generator' => $this->executeContentGenerator($action, $params, $userId),
                'content-analyzer' => $this->executeContentAnalyzer($action, $params, $userId),
                'wordpress-publisher' => $this->executeWordPressPublisher($action, $params, $userId),
                'image-fetcher' => $this->executeImageFetcher($action, $params, $userId),
                'google-trends' => $this->executeGoogleTrends($action, $params, $userId),
                default => ['status' => 'error', 'message' => "Tool {$toolSlug} belum diimplementasikan."],
            };

            $duration = round((microtime(true) - $start) * 1000);

            return array_merge([
                'tool' => $toolSlug,
                'action' => $action,
                'duration_ms' => $duration,
                'status' => 'ok',
            ], $result);
        } catch (Exception $e) {
            Log::error('AgentConnector: tool execution failed', [
                'tool' => $toolSlug,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return [
                'tool' => $toolSlug,
                'action' => $action,
                'status' => 'error',
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000),
            ];
        }
    }

    public function getToolRegistry(): array
    {
        $this->ensureToolRegistrySynced();

        return AgentToolRegistry::active()->get()->toArray();
    }

    public function syncToolRegistry(): int
    {
        $tools = $this->defaultTools();

        foreach ($tools as $tool) {
            AgentToolRegistry::updateOrCreate(
                ['slug' => $tool['slug']],
                $tool,
            );
        }

        return count($tools);
    }

    protected function ensureToolRegistrySynced(): void
    {
        if (!AgentToolRegistry::exists()) {
            $this->syncToolRegistry();
        }
    }

    protected function defaultTools(): array
    {
        return [
            [
                'name' => 'Keyword Clusters',
                'slug' => 'keyword-clusters',
                'description' => 'Manage keyword clusters, track progress, auto-process keywords untuk konten otomatis',
                'capabilities' => [
                    ['action' => 'list', 'description' => 'Lihat semua cluster'],
                    ['action' => 'create', 'description' => 'Buat cluster baru dengan daftar keyword'],
                    ['action' => 'detail', 'description' => 'Lihat detail cluster termasuk progress'],
                    ['action' => 'start', 'description' => 'Mulai otomasi cluster'],
                    ['action' => 'stop', 'description' => 'Hentikan otomasi cluster'],
                    ['action' => 'add_keyword', 'description' => 'Tambah keyword ke cluster'],
                    ['action' => 'status', 'description' => 'Progress cluster'],
                ],
                'order' => 1,
            ],
            [
                'name' => 'Keyword Research',
                'slug' => 'keyword-research',
                'description' => 'Riset LSI keywords dan entities dari target keyword menggunakan AI',
                'capabilities' => [
                    ['action' => 'research', 'description' => 'Riset keyword, return LSI keywords + entities', 'params' => ['keyword' => 'string', 'locale' => 'string']],
                ],
                'order' => 2,
            ],
            [
                'name' => 'Content Generator',
                'slug' => 'content-generator',
                'description' => 'Generate artikel 3-phase (draft konten, critical questions, final artikel)',
                'capabilities' => [
                    ['action' => 'generate', 'description' => 'Generate artikel dari keyword', 'params' => ['keyword', 'tone', 'locale', 'lsi_keywords', 'entities']],
                ],
                'order' => 3,
            ],
            [
                'name' => 'Content Analyzer',
                'slug' => 'content-analyzer',
                'description' => 'Analisa kualitas konten: SEO score, struktur artikel, readability, dan analisis gambar',
                'capabilities' => [
                    ['action' => 'analyze', 'description' => 'Analisa konten', 'params' => ['content' => 'text', 'keyword' => 'string']],
                ],
                'order' => 4,
            ],
            [
                'name' => 'WordPress Publisher',
                'slug' => 'wordpress-publisher',
                'description' => 'Publish artikel dan upload gambar ke WordPress via REST API',
                'capabilities' => [
                    ['action' => 'publish', 'description' => 'Post artikel ke WordPress'],
                ],
                'order' => 5,
            ],
            [
                'name' => 'Image Fetcher',
                'slug' => 'image-fetcher',
                'description' => 'Cari gambar dari DuckDuckGo/Bing/Google dan upload ke WordPress',
                'capabilities' => [
                    ['action' => 'search', 'description' => 'Cari & tampilkan gambar', 'params' => ['keyword' => 'string', 'count' => 'int', 'source' => 'string']],
                ],
                'order' => 6,
            ],
            [
                'name' => 'Google Trends',
                'slug' => 'google-trends',
                'description' => 'Cek tren keyword di Google (butuh integrasi eksternal)',
                'capabilities' => [
                    ['action' => 'trend', 'description' => 'Cek tren keyword'],
                ],
                'order' => 7,
            ],
        ];
    }

    public function buildSystemPrompt(): string
    {
        $tools = $this->getToolRegistry();
        $desc = '';

        foreach ($tools as $t) {
            $desc .= "- {$t['name']} ({$t['slug']}): {$t['description']}\n";
        }

        return "Anda adalah Agent Connector. Tool yang tersedia:\n{$desc}";
    }

    private function getOrCreateSession(int $userId, string $sessionId): AgentSession
    {
        $session = AgentSession::where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->first();

        if (!$session) {
            $session = AgentSession::create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
        } else {
            $session->touch();
        }

        return $session;
    }

    private function buildResponse(array $intent, array $actions, array $memories, string $input): string
    {
        if ($intent['intent'] === 'help') {
            return $this->buildHelpResponse();
        }

        if ($intent['intent'] === 'general_chat') {
            return $this->buildNaturalChatResponse($input, $memories);
        }

        $response = '';

        foreach ($actions as $action) {
            if ($action['status'] === 'error') {
                $response .= "⚠️ " . ($action['message'] ?? 'Terjadi kesalahan') . "\n\n";
            } elseif (!empty($action['message'])) {
                $response .= $action['message'] . "\n";
                if (!empty($action['data'])) {
                    $response .= $this->formatToolData($action['data']) . "\n";
                }
            } elseif (!empty($action['data'])) {
                $response .= $this->formatToolData($action['data']) . "\n\n";
            }
        }

        if (empty($response)) {
            $response = ($intent['reasoning'] ?? 'Siap') . "\n\nGunakan perintah bantuan untuk lihat fitur.";
        }

        return trim($response);
    }

    private function buildHelpResponse(): string
    {
        $knowledge = app(ProductKnowledgeService::class);
        $lines = ["Saya bisa bantu mengelola SEO konten Anda. Ini yang bisa saya kerjakan:\n"];

        foreach ($knowledge->modules() as $m) {
            $lines[] = "📦 {$m['name']} ({$m['slug']})";
            $lines[] = "   {$m['what']}";
            foreach (array_slice($m['actions'], 0, 3) as $a) {
                $lines[] = "   • {$a['example']}";
            }
            $lines[] = '';
        }

        $lines[] = "🧩 Alur kerja umum:";
        foreach ($knowledge->workflows()[0]['steps'] as $s) {
            $lines[] = "   • {$s}";
        }

        return trim(implode("\n", $lines));
    }

    private function formatToolData(array $data): string
    {
        $lines = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $label = str_replace(['_', '-'], ' ', (string) $key);
                $lines[] = "  • {$label}:";
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $lines[] = "    - " . implode(', ', array_map(fn ($v) => is_scalar($v) ? $v : json_encode($v), $item));
                    } else {
                        $lines[] = "    - {$item}";
                    }
                }
            } else {
                $label = str_replace(['_', '-'], ' ', (string) $key);
                $lines[] = "  • {$label}: {$value}";
            }
        }

        return implode("\n", $lines);
    }

    private function executeKeywordCluster(string $action, array $params, int $userId): array
    {
        $service = app(\Modules\SeoCluster\Services\ClusterService::class);

        return match ($action) {
            'list' => [
                'data' => $service->getAutomationSummary(),
                'message' => 'Berikut daftar cluster Anda.',
            ],
            'status' => [
                'data' => $service->getClusterProgress((int) ($params['id'] ?? 0)),
                'message' => 'Progress cluster.',
            ],
            'create' => [
                'message' => $this->createClusterFromParams($service, $params, $userId),
            ],
            'start' => $this->toggleCluster($service, $params, 'active'),
            'stop' => $this->toggleCluster($service, $params, 'paused'),
            'add_keyword' => $this->addKeywordToCluster($service, $params),
            default => ['message' => 'Action tidak dikenal untuk keyword-clusters.'],
        };
    }

    private function createClusterFromParams($service, array $params, int $userId): string
    {
        $parent = $params['parent_keyword'] ?? $params['keyword'] ?? null;

        if (!$parent && !empty($params['topic'])) {
            try {
                $structureService = app(\Modules\SeoCluster\Services\ClusterStructureService::class);
                $parentCount = (int) ($params['parent_count'] ?? 4);
                $childCount = (int) ($params['child_count'] ?? 4);

                $clusters = $structureService->generateStructure(
                    userId: $userId,
                    topic: $params['topic'],
                    parentCount: $parentCount,
                    childCount: $childCount,
                );

                if (empty($clusters)) {
                    return 'Gagal membuat cluster dari topik "' . $params['topic'] . '". Silakan coba lagi.';
                }

                $lines = collect($clusters)->map(fn ($c) => "• {$c->name} (#{$c->id}) — {$c->total_keywords} child keyword")->implode("\n");

                return "Berhasil membuat {$parentCount} cluster dari topik \"{$params['topic']}\":\n\n{$lines}\n\nKetik \"aktifkan cluster\" untuk mulai memproses.";
            } catch (Exception $e) {
                Log::error('AgentConnector: generate cluster structure gagal', ['error' => $e->getMessage()]);
                return 'Gagal membuat cluster: ' . $e->getMessage();
            }
        }

        if (!$parent) {
            return 'Sertakan parent_keyword atau topik untuk membuat cluster. Contoh: "buat cluster belajar SEO website dengan 4 parent dan 5 child per parent".';
        }

        $keywords = $params['keywords'] ?? [];
        if (is_string($keywords)) {
            $keywords = array_filter(array_map('trim', explode(',', $keywords)));
        }
        if (empty($keywords)) {
            $keywords = [$parent];
        }

        $cluster = $service->createCluster(
            userId: $userId,
            name: $params['name'] ?? "Cluster: {$parent}",
            parentKeyword: $parent,
            keywords: $keywords,
            description: $params['description'] ?? null,
            schedule: $params['schedule'] ?? 'manual',
            imageKeyword: $params['image_keyword'] ?? null,
            imageSource: $params['image_source'] ?? 'duckduckgo',
            imagePerArticle: (int) ($params['image_per_article'] ?? 3),
        );

        return "Cluster '{$cluster->name}' (#{$cluster->id}) dibuat dengan {$cluster->total_keywords} keyword.";
    }

    private function toggleCluster($service, array $params, string $status): array
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$id) {
            return ['message' => 'Sertakan id cluster.'];
        }

        if ($status === 'active') {
            $service->activateCluster($id);
        } else {
            $service->pauseCluster($id);
        }

        return ['message' => "Cluster #{$id} sekarang {$status}."];
    }

    private function addKeywordToCluster($service, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $keyword = trim($params['keyword'] ?? '');
        if (!$id || !$keyword) {
            return ['message' => 'Sertakan id dan keyword.'];
        }

        $service->addKeyword($id, $keyword);

        return ['message' => "Keyword '{$keyword}' ditambahkan ke cluster #{$id}."];
    }

    private function executeKeywordResearch(string $action, array $params, int $userId): array
    {
        if ($action !== 'research' || empty($params['keyword'])) {
            return ['message' => 'Silakan sertakan keyword untuk diriset.'];
        }

        $service = app(\Modules\KeywordResearch\Services\KeywordResearchService::class);
        $result = $service->research(
            keyword: $params['keyword'],
            locale: $params['locale'] ?? 'id',
        );

        return [
            'data' => $result,
            'message' => "Riset keyword '{$params['keyword']}' selesai. Ditemukan " . count($result['lsi_keywords'] ?? []) . " LSI keywords dan " . count($result['entities'] ?? []) . " entities.",
        ];
    }

    private function executeContentGenerator(string $action, array $params, int $userId): array
    {
        if ($action !== 'generate' || empty($params['keyword'])) {
            return ['message' => 'Sertakan keyword untuk generate konten.'];
        }

        $keyword = $params['keyword'];
        $locale = $params['locale'] ?? 'id';
        $tone = $params['tone'] ?? 'informative';
        $lsi = $params['lsi_keywords'] ?? [];
        $entities = $params['entities'] ?? [];

        if (is_string($lsi)) {
            $lsi = array_filter(array_map('trim', explode(',', $lsi)));
        }
        if (is_string($entities)) {
            $entities = array_filter(array_map('trim', explode(',', $entities)));
        }

        $service = app(\Modules\ContentGenerator\Services\ContentGeneratorService::class);

        $phase1 = $service->generatePhase1($keyword, $locale, $tone, $lsi, $entities, $userId);
        $questions = $service->generatePhase2($phase1, $keyword);
        $phase3 = $service->generatePhase3($phase1, $questions, $keyword, $locale, $tone, $lsi, $entities);
        $meta = $service->generateMetaData($phase3, $keyword, $locale);

        $generation = ContentGeneration::create([
            'user_id' => $userId,
            'target_keyword' => $keyword,
            'locale' => $locale,
            'tone' => $tone,
            'lsi_keywords' => $lsi,
            'entities' => $entities,
            'phase_1_content' => $phase1,
            'phase_2_questions' => $questions,
            'phase_3_content' => $phase3,
            'meta_title' => $meta['title'] ?? null,
            'meta_description' => $meta['description'] ?? null,
            'status' => 'completed',
            'current_phase' => 3,
        ]);

        app(\Modules\ContentGenerator\Services\MemoryService::class)->storeFromGeneration($generation);

        return [
            'data' => [
                'generation_id' => $generation->id,
                'title' => $meta['title'] ?? null,
                'description' => $meta['description'] ?? null,
                'word_count' => str_word_count(strip_tags($phase3)),
                'phase_3_length' => strlen($phase3),
            ],
            'message' => "Konten untuk '{$keyword}' selesai dibuat (generation #{$generation->id}). " . str_word_count(strip_tags($phase3)) . ' kata.',
        ];
    }

    private function latestGeneration(int $userId): ?ContentGeneration
    {
        return ContentGeneration::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function executeContentAnalyzer(string $action, array $params, int $userId): array
    {
        $content = $params['content'] ?? null;
        if (!$content && !empty($params['generation_id'])) {
            $generation = ContentGeneration::where('user_id', $userId)->find($params['generation_id']);
            $content = $generation->phase_3_content ?? null;
        }

        if (!$content) {
            $generation = $this->latestGeneration($userId);
            $content = $generation->phase_3_content ?? null;
            if ($content) {
                $params['generation_id'] = $generation->id;
            }
        }

        if (!$content) {
            return ['message' => 'Sertakan content atau generation_id untuk dianalisa. Contoh: "analisa konten: <teks>" atau "analisa konten terakhir saya".'];
        }

        $generator = app(\Modules\ContentGenerator\Services\ContentGeneratorService::class);
        $meta = $generator->generateMetaData($content, $params['keyword'] ?? 'konten', $params['locale'] ?? 'id');

        $wordCount = str_word_count(strip_tags($content));
        $headings = preg_match_all('/<h[1-6][^>]*>/i', $content) ?: 0;
        $paragraphs = preg_match_all('/<p[^>]*>/i', $content) ?: 0;
        $images = preg_match_all('/<img[^>]*>/i', $content) ?: 0;
        $links = preg_match_all('/<a[^>]*>/i', $content) ?: 0;

        $suggestions = [];
        if ($wordCount < 600) {
            $suggestions[] = 'Konten kurang dari 600 kata, pertimbangkan memperdalam.';
        }
        if ($headings === 0) {
            $suggestions[] = 'Tidak ada heading, tambahkan hierarki H2/H3.';
        }
        if ($images === 0) {
            $suggestions[] = 'Tidak ada gambar, tambahkan minimal 1 gambar relevan.';
        }
        if ($links === 0) {
            $suggestions[] = 'Tidak ada link, tambahkan link internal/eksternal.';
        }

        return [
            'data' => [
                'word_count' => $wordCount,
                'headings' => $headings,
                'paragraphs' => $paragraphs,
                'images' => $images,
                'links' => $links,
                'meta_title' => $meta['title'] ?? null,
                'meta_description' => $meta['description'] ?? null,
                'suggestions' => $suggestions,
            ],
            'message' => "Analisa konten selesai: {$wordCount} kata, {$headings} heading, {$images} gambar, {$links} link.",
        ];
    }

    private function executeWordPressPublisher(string $action, array $params, int $userId): array
    {
        if ($action !== 'publish') {
            return ['message' => 'Action tidak dikenal untuk wordpress-publisher.'];
        }

        $content = $params['content'] ?? null;
        if (!$content && !empty($params['generation_id'])) {
            $generation = ContentGeneration::where('user_id', $userId)->find($params['generation_id']);
            $content = $generation->phase_3_content ?? null;
        }

        if (!$content) {
            $generation = $this->latestGeneration($userId);
            $content = $generation->phase_3_content ?? null;
            if ($content) {
                $params['generation_id'] = $generation->id;
            }
        }

        if (!$content) {
            return ['message' => 'Sertakan content atau generation_id untuk dipublish. Contoh: "publish konten terakhir saya" atau "publish konten: <teks>".'];
        }

        $wpUrl = Setting::getValue('seo-agent.wp.url', config('seo-cluster.wp.url', ''));
        if (!$wpUrl) {
            return ['message' => 'Tidak ada koneksi WordPress. Hubungkan situs Anda di pengaturan dulu.'];
        }

        $wpService = new \Modules\SeoCluster\Services\WordPressService();
        $title = $params['title'] ?? (($generation ?? null)?->meta_title ?? ($params['keyword'] ?? 'Konten baru'));

        $result = $wpService->publishPost($title, $content, [
            'status' => $params['status'] ?? 'publish',
        ]);

        if (empty($result['id'])) {
            return ['message' => 'Gagal publish: ' . ($result['error'] ?? 'respon tidak dikenal')];
        }

        $images = (new \Modules\SeoCluster\Services\ImageService())
            ->fetchAndUpload($params['keyword'] ?? $title, $wpService, 1, 'bing');

        if ($generation ?? null) {
            $generation->update(['status' => 'published']);
        }

        return [
            'data' => [
                'post_id' => $result['id'],
                'url' => $result['link'] ?? null,
                'images_uploaded' => count($images['success'] ?? []),
            ],
            'message' => "Artikel '{$title}' berhasil dipublish ke WordPress (post #{$result['id']}).",
        ];
    }

    private function executeImageFetcher(string $action, array $params, int $userId): array
    {
        if ($action !== 'search' || empty($params['keyword'])) {
            return ['message' => 'Sertakan keyword untuk pencarian gambar.'];
        }

        $source = $params['source'] ?? 'bing';
        $count = (int) ($params['count'] ?? 3);
        $service = new \Modules\SeoCluster\Services\ImageService();

        $results = match ($source) {
            'google' => $service->searchImages($params['keyword'], $count),
            'duckduckgo' => $service->searchDuckDuckGo($params['keyword'], $count),
            default => $service->searchBingImages($params['keyword'], $count),
        };

        return [
            'data' => $results,
            'message' => "Ditemukan " . count($results) . " gambar untuk '{$params['keyword']}' dari {$source}.",
        ];
    }

    private function executeGoogleTrends(string $action, array $params, int $userId): array
    {
        return ['message' => 'Fitur Google Trends belum tersedia tanpa API key eksternal. Gunakan keyword-research untuk data alternatif.'];
    }

    private function formatCapabilities(array $caps): string
    {
        $lines = [];
        foreach ($caps as $c) {
            $action = $c['action'] ?? '';
            $desc = $c['description'] ?? '';
            $lines[] = "   * {$action}: {$desc}";
        }
        return $lines ? "\n" . implode("\n", $lines) : '';
    }

    private function parseJson(string $raw): ?array
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

    private function buildNaturalChatResponse(string $input, array $memories): string
    {
        $memoryText = collect($memories)->map(fn ($m) => "[{$m['type']}] {$m['content']}")->implode("\n");

        $productKnowledge = app(ProductKnowledgeService::class)->markdown();
        $examples = app(ProductKnowledgeService::class)->examplesMarkdown();

        $systemPrompt = <<<PROMPT
Anda adalah Agent Connector, asisten AI yang menjadi otak sistem SEO tools.

{$productKnowledge}

{$examples}

Tugas Anda sekarang: menjawab percakapan biasa secara natural, hangat, dan tidak kaku dalam bahasa Indonesia — seperti teman yang membantu, bukan robot.
Panduan:
- Balas sapaan, ucapan terima kasih, dan basa-basi dengan ramah dan singkat. Jangan langsung menawarkan daftar tool.
- Jika user bertanya tentang kemampuan/fitur/cara pakai sistem, jelaskan ringkas berdasarkan panduan produk di atas, pakai bahasa manusiawi.
- Gunakan konteks memory percakapan sebelumnya jika relevan.
- Jangan menyebutkan istilah teknis (slug, action, params) kecuali user bertanya.
- Jawaban singkat (1-3 kalimat), ramah, dan membantu.

KONTEKS MEMORY PERCAKAPAN SEBELUMNYA:
{$memoryText}
PROMPT;

        try {
            $reply = trim($this->callAI($systemPrompt, $input));

            return $reply !== '' ? $reply : 'Halo! Ada yang bisa saya bantu untuk SEO konten Anda?';
        } catch (Exception $e) {
            Log::warning('AgentConnector: natural chat AI gagal, pakai fallback', ['error' => $e->getMessage()]);

            return 'Halo! Ada yang bisa saya bantu untuk SEO konten Anda?';
        }
    }

    private function callAI(string $systemPrompt, string $userPrompt): string
    {
        $ai = Setting::aiConfig();
        $url = $ai['url'];
        $apiKey = $ai['api_key'];
        $model = $ai['chat_model'];

        if (!$url) {
            throw new Exception('AI URL is not configured.');
        }

        $endpoint = str_ends_with(rtrim($url, '/'), '/v1') ? rtrim($url, '/') . '/chat/completions' : rtrim($url, '/') . '/v1/chat/completions';

        $maxAttempts = 2;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(60)
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
                        'temperature' => 0.3,
                        'max_tokens' => 2048,
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
                Log::warning('AgentConnector: retry callAI', ['attempt' => $attempt, 'error' => $lastError->getMessage()]);
                sleep(2);
            }
        }

        throw $lastError ?? new Exception('Gagal terhubung ke AI.');
    }
}
