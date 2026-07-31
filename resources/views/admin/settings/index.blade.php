@extends('layouts.app')

@section('title', 'AI Settings')

@section('content')
@php
    $groupLabels = [
        'ai-providers' => 'AI Providers',
        'ai' => 'AI General',
        'keyword-research' => 'Keyword Research',
        'content-generator' => 'Content Generator',
        'seo-agent' => 'SEO Agent (Telegram)',
    ];

    $generalKeys = ['ai.default_provider', 'ai.credits_per_generation', 'ai.font_path'];

    $aiProviderGroup = $groups['ai-providers'] ?? collect();
    $aiGeneralSettings = collect($groups['ai'] ?? [])
        ->concat($aiProviderGroup->filter(fn ($s) => in_array($s['key'], $generalKeys)))
        ->values();

    $tabs = [
        ['id' => 'general', 'label' => 'AI General', 'settings' => $aiGeneralSettings],
    ];

    foreach ($providers as $provider) {
        $providerSettings = $aiProviderGroup->filter(fn ($s) => str_starts_with($s['key'], "ai.{$provider}."))->values();
        $tabs[] = [
            'id' => "provider-{$provider}",
            'label' => ucfirst($provider),
            'provider' => $provider,
            'settings' => $providerSettings,
        ];
    }

    foreach (['keyword-research', 'content-generator', 'seo-agent'] as $groupName) {
        if (($groups[$groupName] ?? collect())->isNotEmpty()) {
            $tabs[] = [
                'id' => "group-{$groupName}",
                'label' => $groupLabels[$groupName] ?? ucfirst($groupName),
                'settings' => $groups[$groupName]->values(),
            ];
        }
    }

    $activeTab = request()->query('tab', 'general');
    $activeProviders = [];
    foreach ($providers as $provider) {
        $val = Setting::getValue("ai.{$provider}.is_active", false);
        $activeProviders[$provider] = $val === true || $val === 'true';
    }
@endphp

<div class="flex flex-col lg:flex-row gap-6">
    <div class="lg:w-64 shrink-0">
        <nav class="space-y-1">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">AI Configuration</div>
            @foreach ($tabs as $index => $tab)
                <button type="button" data-tab-target="{{ $tab['id'] }}"
                    class="tab-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition flex items-center justify-between {{ $activeTab === $tab['id'] ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    <span>
                        @if (!empty($tab['provider']))
                            <span class="inline-block w-2 h-2 rounded-full mr-2 {{ ($activeProviders[$tab['provider']] ?? false) ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                        @endif
                        {{ $tab['label'] }}
                    </span>
                </button>
            @endforeach
            <div class="pt-3 border-t border-gray-200 mt-3">
                <button type="button" data-tab-target="add-provider"
                    class="tab-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 {{ $activeTab === 'add-provider' ? 'bg-indigo-600 text-white' : 'text-indigo-600 hover:bg-indigo-50' }}">
                    <span class="text-lg leading-none">+</span> Tambah AI Provider
                </button>
            </div>
        </nav>
    </div>

    <div class="flex-1 min-w-0">
        <div id="tab-panels">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf

                @foreach ($tabs as $tab)
                    <div data-tab-panel="{{ $tab['id'] }}" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden {{ $activeTab === $tab['id'] ? '' : 'hidden' }}">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                            <h2 class="font-semibold text-gray-900">{{ $tab['label'] }}</h2>
                            @if (!empty($tab['provider']))
                                <form method="POST" action="{{ route('admin.settings.provider.delete') }}" onsubmit="return confirm('Hapus provider {{ $tab['provider'] }}?')">
                                    @csrf
                                    <input type="hidden" name="slug" value="{{ $tab['provider'] }}">
                                    <button type="submit" class="text-red-500 text-xs font-medium hover:underline">Hapus Provider</button>
                                </form>
                            @endif
                        </div>
                        <div class="p-6 space-y-5">
                            @foreach ($tab['settings'] as $setting)
                                <div>
                                    <label for="s_{{ Str::slug($setting['key'], '_') }}" class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ $setting['description'] ?: $setting['key'] }}
                                    </label>

                                    @if ($setting['type'] === 'boolean')
                                        <select name="settings[{{ $setting['key'] }}]" id="s_{{ Str::slug($setting['key'], '_') }}"
                                            class="w-full max-w-xs px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <option value="true" {{ $setting['value'] === 'true' || $setting['value'] === true ? 'selected' : '' }}>Enabled</option>
                                            <option value="false" {{ $setting['value'] === 'false' || $setting['value'] === false ? 'selected' : '' }}>Disabled</option>
                                        </select>
                                    @elseif ($setting['type'] === 'password')
                                        <div class="flex max-w-lg">
                                            <input type="password" name="settings[{{ $setting['key'] }}]" id="s_{{ Str::slug($setting['key'], '_') }}"
                                                value="{{ $setting['value'] }}"
                                                class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm">
                                            <button type="button" onclick="this.previousElementSibling.type='text';this.textContent='🙈';setTimeout(()=>{this.previousElementSibling.type='password';this.textContent='👁'},3000)"
                                                class="ml-2 px-3 border border-gray-300 rounded-lg text-sm text-gray-500 hover:bg-gray-50 transition">👁</button>
                                        </div>
                                    @else
                                        <input type="{{ $setting['type'] === 'url' ? 'url' : 'text' }}" name="settings[{{ $setting['key'] }}]"
                                            id="s_{{ Str::slug($setting['key'], '_') }}" value="{{ $setting['value'] }}"
                                            class="w-full max-w-lg px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                            {{ $setting['type'] === 'url' ? 'placeholder=https://' : '' }}>
                                    @endif

                                    <p class="text-xs text-gray-400 mt-1 font-mono">{{ $setting['key'] }}</p>
                                </div>
                            @endforeach

                            @if ($tab['id'] === 'group-seo-agent')
                                <hr class="border-gray-200">
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <input type="text" id="webhook-url" value="{{ url('/api/seo-agent/webhook') }}"
                                            class="flex-1 max-w-md px-4 py-2 border border-gray-300 rounded-lg text-sm font-mono">
                                        <button type="button" onclick="setWebhook()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition shadow-sm">
                                            Set Webhook
                                        </button>
                                    </div>
                                    <div>
                                        <button type="button" onclick="cekWebhook()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
                                            Cek Status Webhook
                                        </button>
                                        <pre id="webhook-info" class="mt-2 text-xs bg-gray-50 p-3 rounded-lg border hidden overflow-auto max-h-40"></pre>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end mt-6">
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition shadow-sm">
                        Save Settings
                    </button>
                </div>
            </form>

            <div data-tab-panel="add-provider" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden {{ $activeTab === 'add-provider' ? '' : 'hidden' }}">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <h2 class="font-semibold text-gray-900">Tambah AI Provider Baru</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.settings.provider') }}" class="space-y-5 max-w-xl">
                        @csrf
                        <div>
                            <label for="new-provider-name" class="block text-sm font-medium text-gray-700 mb-1">Nama Provider</label>
                            <input type="text" id="new-provider-name" name="name" required placeholder="contoh: deepseek, gemini, groq"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                            <p class="text-xs text-gray-400 mt-1">Huruf/angka/underscore. Akan menjadi prefix setting <span class="font-mono">ai.{nama}.*</span></p>
                        </div>
                        <div>
                            <label for="new-provider-url" class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                            <input type="url" id="new-provider-url" name="url" required placeholder="https://api.example.com/v1"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label for="new-provider-key" class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                            <input type="password" id="new-provider-key" name="api_key" placeholder="sk-..."
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label for="new-provider-chat" class="block text-sm font-medium text-gray-700 mb-1">Chat Model</label>
                                <input type="text" id="new-provider-chat" name="chat_model" placeholder="openai/gpt-4o"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>
                            <div>
                                <label for="new-provider-embed" class="block text-sm font-medium text-gray-700 mb-1">Embedding Model</label>
                                <input type="text" id="new-provider-embed" name="embedding_model" placeholder="gemini/gemini-embedding-001"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>
                            <div>
                                <label for="new-provider-img" class="block text-sm font-medium text-gray-700 mb-1">Image Model</label>
                                <input type="text" id="new-provider-img" name="image_model" placeholder="openai/dall-e-3"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-green-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-green-700 transition shadow-sm">
                                Tambah Provider
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function activateTab(id) {
    document.querySelectorAll('[data-tab-panel]').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('[data-tab-target]').forEach(b => {
        const active = b.dataset.tabTarget === id;
        b.classList.toggle('bg-indigo-600', active);
        b.classList.toggle('text-white', active);
        b.classList.toggle('text-gray-600', !active && !b.closest('.pt-3'));
        b.classList.toggle('text-indigo-600', !active && !!b.closest('.pt-3'));
        b.classList.toggle('hover:bg-gray-100', !active && !b.closest('.pt-3'));
        b.classList.toggle('hover:bg-indigo-50', !active && !!b.closest('.pt-3'));
    });
    const panel = document.querySelector(`[data-tab-panel="${id}"]`);
    if (panel) panel.classList.remove('hidden');
    history.replaceState(null, '', new URL(location.pathname + '?tab=' + id, location.href));
}

document.querySelectorAll('[data-tab-target]').forEach(btn => {
    btn.addEventListener('click', () => activateTab(btn.dataset.tabTarget));
});

function cekWebhook() {
    const pre = document.getElementById('webhook-info');
    pre.classList.remove('hidden');
    pre.textContent = 'Loading...';
    fetch('{{ route("admin.settings.telegram-webhook-info") }}')
        .then(r => r.json())
        .then(d => { pre.textContent = JSON.stringify(d, null, 2); })
        .catch(e => { pre.textContent = 'Error: ' + e.message; });
}

function setWebhook() {
    const pre = document.getElementById('webhook-info');
    const url = document.getElementById('webhook-url').value;
    pre.classList.remove('hidden');
    pre.textContent = 'Setting webhook...';
    fetch('{{ route("admin.settings.telegram-webhook") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
        },
        body: JSON.stringify({ url: url })
    })
    .then(r => r.json())
    .then(d => {
        pre.textContent = JSON.stringify(d, null, 2);
        if (d.success) {
            setTimeout(cekWebhook, 1000);
        }
    })
    .catch(e => { pre.textContent = 'Error: ' + e.message; });
}
</script>
@endpush
@endsection
