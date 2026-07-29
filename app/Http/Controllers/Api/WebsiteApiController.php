<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Websites\Website;
use App\Models\Websites\WebsiteTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebsiteApiController extends Controller
{
    public function index()
    {
        $websites = Auth::user()->websites()
            ->withCount(['tools' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $websites]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $existing = Auth::user()->websites()
            ->where('domain', $validated['domain'])
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Domain already registered.'], 409);
        }

        $website = Auth::user()->websites()->create($validated);

        return response()->json([
            'message' => 'Website registered successfully.',
            'data' => $website,
        ], 201);
    }

    public function show(Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $website->load(['tools' => function ($q) {
            $q->withPivot('is_active', 'config', 'last_used_at');
        }]);

        return response()->json(['data' => $website]);
    }

    public function update(Request $request, Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $website->update($validated);

        return response()->json([
            'message' => 'Website updated.',
            'data' => $website,
        ]);
    }

    public function destroy(Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $website->delete();

        return response()->json(['message' => 'Website removed.']);
    }

    public function attachTool(Request $request, Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'tool_id' => 'required|integer|exists:tools,id',
            'config' => 'nullable|json',
        ]);

        $website->tools()->syncWithoutDetaching([
            $validated['tool_id'] => [
                'is_active' => true,
                'config' => $validated['config'] ?? null,
            ],
        ]);

        return response()->json(['message' => 'Tool attached.'], 200);
    }

    public function detachTool(Request $request, Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'tool_id' => 'required|integer|exists:tools,id',
        ]);

        $website->tools()->detach($validated['tool_id']);

        return response()->json(['message' => 'Tool detached.']);
    }

    public function generateKey(Request $request, Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'tool_id' => 'required|integer|exists:tools,id',
        ]);

        $result = WebsiteTool::generateApiKey($website->id, $validated['tool_id']);

        return response()->json([
            'message' => 'API key generated.',
            'data' => [
                'website_tool_id' => $result['website_tool']->id,
                'plain_text_key' => $result['plain_text'],
            ],
        ]);
    }

    public function regenerateKey(Request $request, Website $website)
    {
        abort_if($website->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'tool_id' => 'required|integer|exists:tools,id',
        ]);

        $result = WebsiteTool::generateApiKey($website->id, $validated['tool_id']);

        return response()->json([
            'message' => 'API key regenerated.',
            'data' => [
                'website_tool_id' => $result['website_tool']->id,
                'plain_text_key' => $result['plain_text'],
            ],
        ]);
    }
}
