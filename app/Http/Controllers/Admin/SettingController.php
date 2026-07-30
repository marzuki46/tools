<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{
    public function __construct(
        protected TelegramService $telegram,
    ) {
        abort_unless(Auth::user()->is_admin, 403, 'Admin access required.');
    }

    public function index()
    {
        $groups = Setting::get()->groupBy('group');

        return view('admin.settings.index', ['groups' => $groups]);
    }

    public function update(Request $request)
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value);
        }

        return redirect()->route('admin.settings')
            ->with('success', 'Settings updated successfully.');
    }

    public function setTelegramWebhook(Request $request): JsonResponse|RedirectResponse
    {
        $url = $request->input('url', url('/api/seo-agent/webhook'));
        $result = $this->telegram->setWebhook($url);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return redirect()->route('admin.settings')
                ->with('success', 'Webhook Telegram berhasil diset.');
        }

        return redirect()->route('admin.settings')
            ->with('error', 'Gagal set webhook: ' . ($result['message'] ?? 'unknown error'));
    }

    public function telegramWebhookInfo(): JsonResponse
    {
        return response()->json($this->telegram->getWebhookInfo());
    }
}
