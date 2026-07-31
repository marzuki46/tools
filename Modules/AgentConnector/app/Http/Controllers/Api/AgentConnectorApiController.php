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
        $count = $this->agentService->syncToolRegistry();

        return response()->json(['success' => true, 'message' => $count . ' tools berhasil disinkronisasi.']);
    }
}
