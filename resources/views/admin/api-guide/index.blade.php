@extends('layouts.app')

@section('title', 'API Connection Guide')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">API Connection Guide</h1>
        <p class="text-gray-500 text-sm mt-1">Endpoint terpusat untuk mengakses semua modul yang aktif di masing-masing user</p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">Endpoint</h2>
        <div class="space-y-3">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Base URL</p>
                <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200">https://tools.juki.eu.org/api/v1/tool/{slug}/{action}</pre>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Authentication</p>
                <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200">X-API-Key: juki_{key}</pre>
                <p class="text-xs text-gray-400 mt-1">Atau <code class="text-xs bg-gray-200 px-1 rounded">Bearer juki_{key}</code></p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">Daftar Modul & Action</h2>
        <div class="space-y-4">
            @forelse ($tools as $tool)
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-900">{{ $tool->name }}</span>
                    <span class="text-xs bg-gray-100 px-2 py-0.5 rounded font-mono text-gray-600">{{ $tool->slug }}</span>
                </div>
                <div class="mt-2 text-sm text-gray-600">{{ $tool->description }}</div>

                @php
                    $actions = [
                        'keyword-research' => [
                            ['action' => 'research', 'method' => 'POST', 'body' => '{"keyword":"...","locale":"id","lsi_count":12,"entities_count":7}'],
                            ['action' => 'status', 'method' => 'POST', 'body' => '{"id": 1}'],
                        ],
                        'content-generator' => [
                            ['action' => 'generate', 'method' => 'POST', 'body' => '{"keyword":"...","locale":"id","tone":"informative","lsi_keywords":[],"entities":[]}'],
                            ['action' => 'status', 'method' => 'POST', 'body' => '{"id": 1}'],
                        ],
                    ][$tool->slug] ?? [];
                @endphp

                @if ($actions)
                <div class="mt-3 space-y-2">
                    @foreach ($actions as $a)
                    <pre class="bg-gray-50 p-2 rounded text-xs font-mono border border-gray-200 overflow-x-auto">{{ $a['method'] }} /api/v1/tool/{{ $tool->slug }}/{{ $a['action'] }}
Body: {{ $a['body'] }}</pre>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-gray-400 mt-2 italic">Belum ada action yang terdaftar untuk modul ini.</p>
                @endif

                <div class="mt-3 pt-3 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase">User dengan akses aktif</p>
                    @if ($tool->users->count())
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($tool->users as $user)
                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 rounded text-xs font-medium">
                            {{ $user->name }}
                            <a href="{{ route('admin.users.edit', $user) }}" class="hover:text-indigo-900">&rarr;</a>
                        </span>
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-gray-400 mt-1 italic">Tidak ada user yang aktif.</p>
                    @endif
                </div>
            </div>
            @empty
            <p class="text-gray-500 text-sm">Belum ada modul yang aktif.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">Contoh Lengkap</h2>
        <div class="space-y-3">
            <p class="text-sm text-gray-600">Buat riset keyword baru:</p>
            <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 overflow-x-auto">curl -X POST https://tools.juki.eu.org/api/v1/tool/keyword-research/research \
  -H "X-API-Key: juki_{key}" \
  -H "Content-Type: application/json" \
  -d '{"keyword": "kopi nusantara", "locale": "id", "lsi_count": 12, "entities_count": 7}'</pre>

            <p class="text-sm text-gray-600 mt-3">Cek status riset:</p>
            <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 overflow-x-auto">curl -X POST https://tools.juki.eu.org/api/v1/tool/keyword-research/status \
  -H "X-API-Key: juki_{key}" \
  -H "Content-Type: application/json" \
  -d '{"id": 1}'</pre>
        </div>
    </div>

    <div class="bg-yellow-50 border border-yellow-200 p-5 rounded-xl">
        <h3 class="font-semibold text-yellow-800 text-sm">Catatan</h3>
        <ul class="text-sm text-yellow-700 mt-2 list-disc list-inside space-y-1">
            <li>User hanya bisa mengakses modul yang sudah diaktifkan untuknya (via <a href="{{ route('admin.tools') }}" class="underline">Admin Tools</a>).</li>
            <li>API Key bisa dibuat dan dikelola di halaman <a href="{{ route('api-keys.index') }}" class="underline">API Keys</a> masing-masing user.</li>
            <li>Format key: <code class="bg-yellow-100 px-1 rounded text-xs">juki_{random}</code>.</li>
        </ul>
    </div>
</div>
@endsection
