<?php

namespace Modules\AgentConnector\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AgentConnector\Services\AgentConnectorService;
use Modules\AgentConnector\Services\ContentAnalyzerService;

class AgentConnectorController extends Controller
{
    public function __construct(
        private readonly AgentConnectorService $agentService,
        private readonly ContentAnalyzerService $analyzerService,
    ) {}

    public function index()
    {
        return view('agentconnector::index');
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $result = $this->agentService->processInput(
            userId: auth()->id(),
            input: $request->message,
            source: 'web',
            sessionId: 'web_' . auth()->id(),
        );

        return response()->json($result);
    }

    public function analyzer()
    {
        return view('agentconnector::analyzer');
    }

    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:50000',
            'keyword' => 'nullable|string|max:255',
        ]);

        $result = $this->analyzerService->analyze(
            content: $request->content,
            keyword: $request->keyword ?? '',
        );

        return response()->json($result);
    }
}
