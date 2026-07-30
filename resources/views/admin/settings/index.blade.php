@extends('layouts.app')

@section('title', 'AI Provider Settings')

@section('content')
<div class="space-y-6">
    <p class="text-gray-500 text-sm">Configure AI providers for image generation tools</p>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf

        @php
            $groupLabels = [
                'ai-providers' => 'AI Providers Configuration',
                'keyword-research' => 'Keyword Research',
                'content-generator' => 'Content Generator',
                'seo-agent' => 'SEO Agent (Telegram)',
            ];
        @endphp

        @foreach ($groups as $group => $settings)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <h2 class="font-semibold text-gray-900">{{ $groupLabels[$group] ?? ucfirst($group) }}</h2>
                </div>
                <div class="p-6 space-y-5">
                    @foreach ($settings as $setting)
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

                    @if ($group === 'seo-agent')
                        <hr class="border-gray-200">
                        <div class="space-y-3">
                            <form method="POST" action="{{ route('admin.settings.telegram-webhook') }}" class="flex items-center gap-3">
                                @csrf
                                <input type="text" name="url" value="{{ url('/api/seo-agent/webhook') }}"
                                    class="flex-1 max-w-md px-4 py-2 border border-gray-300 rounded-lg text-sm font-mono">
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition shadow-sm">
                                    Set Webhook
                                </button>
                            </form>
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

        <div class="flex justify-end">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition shadow-sm">
                Save Settings
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function cekWebhook() {
    const pre = document.getElementById('webhook-info');
    pre.classList.remove('hidden');
    pre.textContent = 'Loading...';
    fetch('{{ route("admin.settings.telegram-webhook-info") }}')
        .then(r => r.json())
        .then(d => { pre.textContent = JSON.stringify(d, null, 2); })
        .catch(e => { pre.textContent = 'Error: ' + e.message; });
}
</script>
@endpush
@endsection
