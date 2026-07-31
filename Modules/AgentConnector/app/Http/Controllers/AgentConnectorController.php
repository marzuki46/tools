<?php

namespace Modules\AgentConnector\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AgentConnector\Jobs\ProcessAgentMessage;
use Modules\AgentConnector\Models\AgentChatMessage;
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

        $userId = auth()->id();
        $sessionId = 'web_' . $userId;

        $userMessage = AgentChatMessage::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $request->message,
            'status' => 'completed',
        ]);

        $assistantMessage = AgentChatMessage::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'status' => 'queued',
            'stage' => 'Menyiapkan pemrosesan...',
        ]);

        ProcessAgentMessage::dispatch(
            messageId: $assistantMessage->id,
            userId: $userId,
            sessionId: $sessionId,
            input: $request->message,
        );

        return response()->json([
            'message_id' => $assistantMessage->id,
            'session_id' => $sessionId,
        ]);
    }

    public function messageStatus(int $id): JsonResponse
    {
        $message = AgentChatMessage::where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json($message);
    }

    public function history(): JsonResponse
    {
        $sessionId = 'web_' . auth()->id();

        $messages = AgentChatMessage::where('user_id', auth()->id())
            ->where('session_id', $sessionId)
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $messages]);
    }

    public function clearHistory(): JsonResponse
    {
        $sessionId = 'web_' . auth()->id();

        AgentChatMessage::where('user_id', auth()->id())
            ->where('session_id', $sessionId)
            ->delete();

        return response()->json(['success' => true, 'message' => 'Riwayat chat dibersihkan.']);
    }

    public function analyzer()
    {
        $reports = \Modules\AgentConnector\Models\ContentAnalysisReport::forUser(auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('agentconnector::analyzer', compact('reports'));
    }

    public function wpPosts(): JsonResponse
    {
        try {
            $posts = app(\Modules\SeoCluster\Services\WordPressService::class)->getExistingPosts(100);
            return response()->json(['success' => true, 'data' => $posts]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function analyzePost(Request $request): JsonResponse
    {
        $request->validate([
            'wp_post_id' => 'required|integer',
            'keyword' => 'nullable|string|max:255',
        ]);

        try {
            $post = app(\Modules\SeoCluster\Services\WordPressService::class)
                ->getPostContent((int) $request->wp_post_id);

            if (empty($post['content'])) {
                return response()->json(['success' => false, 'message' => 'Post tidak memiliki konten.'], 422);
            }

            $result = $this->analyzerService->analyze(
                content: $post['content'],
                keyword: $request->keyword ?? $post['title'] ?? '',
            );

            $report = \Modules\AgentConnector\Models\ContentAnalysisReport::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'wp_post_id' => $post['id'],
                ],
                [
                    'title' => $post['title'],
                    'url' => $post['url'],
                    'keyword' => $request->keyword ?? $post['title'] ?? '',
                    'total_score' => $result['total_score'],
                    'seo_score' => $result['seo_score'],
                    'structure_score' => $result['structure_score'],
                    'readability_score' => $result['readability_score'],
                    'image_score' => $result['image_score'],
                    'details' => $result['details'],
                    'issues' => $result['issues'],
                    'status' => $result['total_score'] >= 70 ? 'ok' : 'needs_optimization',
                    'optimized_at' => null,
                ]
            );

            $result['report_id'] = $report->id;
            $result['post'] = $post;

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function scheduleOptimization(Request $request): JsonResponse
    {
        $request->validate([
            'report_id' => 'required|integer',
            'scheduled_at' => 'required|date|after:now',
        ]);

        $report = \Modules\AgentConnector\Models\ContentAnalysisReport::where('user_id', auth()->id())
            ->findOrFail($request->report_id);

        $report->update([
            'status' => 'scheduled',
            'scheduled_at' => $request->scheduled_at,
        ]);

        return response()->json(['success' => true, 'message' => 'Optimasi dijadwalkan.']);
    }

    public function reports(): JsonResponse
    {
        $reports = \Modules\AgentConnector\Models\ContentAnalysisReport::forUser(auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $reports]);
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
