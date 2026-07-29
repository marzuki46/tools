<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function getSystemPrompt(): JsonResponse
    {
        $prompt = Setting::getValue('ai.system_prompt', '');
        return response()->json([
            'success' => true,
            'data' => ['system_prompt' => $prompt],
        ]);
    }

    public function updateSystemPrompt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'system_prompt' => 'nullable|string|max:5000',
        ]);

        Setting::setValue('ai.system_prompt', $validated['system_prompt'] ?? '');

        return response()->json([
            'success' => true,
            'message' => 'System prompt berhasil diperbarui.',
        ]);
    }
}
