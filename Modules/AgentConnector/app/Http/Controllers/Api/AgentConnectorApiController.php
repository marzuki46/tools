<?php

namespace Modules\AgentConnector\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AgentConnector\Models\AgentMemory;
use Modules\AgentConnector\Models\AgentToolRegistry;
use Modules\AgentConnector\Services\AgentConnectorService;

class AgentConnectorApiController extends Controller
{
    public function __construct(
        private readonly AgentConnectorService $agentService
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $result = $this->agentService->processInput(
            userId: auth()->id(),
            input: $request->message,
            source: 'api',
            sessionId: $request->session_id ?? 'api_' . auth()->id(),
        );

        return response()->json($result);
    }

    public function memories(): JsonResponse
    {
        $memories = AgentMemory::where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')
            ->take(50)
            ->get();

        return response()->json(['data' => $memories]);
    }

    public function tools(): JsonResponse
    {
        return response()->json([
            'data' => AgentToolRegistry::active()->get(),
        ]);
    }

    public function syncTools(Request $request): JsonResponse
    {
        $tools = [
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
                    ['action' => 'generate_content', 'description' => 'Generate dan publish konten dari keyword di cluster'],
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
                    ['action' => 'generate_meta', 'description' => 'Generate meta title & description dari konten'],
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
                    ['action' => 'upload_image', 'description' => 'Upload gambar ke media library WordPress'],
                ],
                'order' => 5,
            ],
            [
                'name' => 'Image Fetcher',
                'slug' => 'image-fetcher',
                'description' => 'Cari gambar dari DuckDuckGo, convert ke WebP, dan upload ke WordPress',
                'capabilities' => [
                    ['action' => 'fetch', 'description' => 'Cari & upload gambar', 'params' => ['keyword' => 'string', 'count' => 'int']],
                ],
                'order' => 6,
            ],
            [
                'name' => 'Google Trends',
                'slug' => 'google-trends',
                'description' => 'Cek tren keyword di Google: minat dari waktu ke waktu, topik terkait, pertanyaan terkait',
                'capabilities' => [
                    ['action' => 'trend', 'description' => 'Cek tren keyword'],
                ],
                'order' => 7,
            ],
        ];

        foreach ($tools as $tool) {
            AgentToolRegistry::updateOrCreate(
                ['slug' => $tool['slug']],
                $tool,
            );
        }

        return response()->json(['success' => true, 'message' => count($tools) . ' tools berhasil disinkronisasi.']);
    }
}
