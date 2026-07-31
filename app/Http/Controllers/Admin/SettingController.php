<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        return view('admin.settings.index', [
            'groups' => $groups,
            'providers' => Setting::providers(),
        ]);
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

    public function addProvider(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'url' => ['nullable', 'url'],
            'api_key' => ['nullable', 'string'],
            'chat_model' => ['nullable', 'string'],
            'embedding_model' => ['nullable', 'string'],
            'image_model' => ['nullable', 'string'],
        ]);

        $slug = Str::slug($validated['name'], '_');
        if (in_array($slug, Setting::providers(), true)) {
            return redirect()->route('admin.settings')
                ->with('error', "Provider '{$slug}' sudah terdaftar.");
        }

        Setting::addProvider($slug, [
            'url' => $validated['url'] ?? '',
            'api_key' => $validated['api_key'] ?? '',
            'chat_model' => $validated['chat_model'] ?? '',
            'embedding_model' => $validated['embedding_model'] ?? '',
            'model' => $validated['image_model'] ?? '',
        ]);

        return redirect()->route('admin.settings', ['tab' => "provider-{$slug}"])
            ->with('success', "Provider '{$slug}' berhasil ditambahkan.");
    }

    public function removeProvider(Request $request): RedirectResponse
    {
        $slug = $request->input('slug');
        if (!$slug) {
            return redirect()->route('admin.settings')
                ->with('error', 'Provider tidak valid.');
        }

        if ($slug === Setting::defaultProvider()) {
            return redirect()->route('admin.settings')
                ->with('error', "Provider '{$slug}' sedang jadi default. Ganti default dulu sebelum dihapus.");
        }

        Setting::removeProvider($slug);

        return redirect()->route('admin.settings')
            ->with('success', "Provider '{$slug}' dihapus.");
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
