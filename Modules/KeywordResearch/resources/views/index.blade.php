@extends('layouts.app')

@section('title', 'Keyword Research')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Keyword Research</h1>
            <p class="text-gray-500 text-sm mt-1">Riset LSI keywords & entitas via AI</p>
        </div>
        <a href="{{ route('keywordresearch.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + Riset Baru
        </a>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-sm text-gray-500">Total</div>
            <div class="text-2xl font-bold">{{ $stats['total'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-sm text-gray-500">Selesai</div>
            <div class="text-2xl font-bold text-green-600">{{ $stats['completed'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-sm text-gray-500">Pending / Gagal</div>
            <div class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</div>
        </div>
    </div>

    {{-- Tabs --}}
    <div id="kr-tabs">
        <div class="border-b border-gray-200 flex gap-0">
            <button onclick="switchTab('research')" id="tab-btn-research" class="px-5 py-3 text-sm font-medium border-b-2 transition border-indigo-600 text-indigo-600">Riset</button>
            <button onclick="switchTab('api')" id="tab-btn-api" class="px-5 py-3 text-sm font-medium border-b-2 transition border-transparent text-gray-500 hover:text-gray-700">API</button>
        </div>

        {{-- Tab: Riset --}}
        <div id="tab-content-research" class="pt-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Keyword</th>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">LSI</th>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Entities</th>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Sumber</th>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($researches as $r)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="p-3 font-medium">{{ $r->target_keyword }}</td>
                            <td class="p-3 text-sm text-gray-600">{{ $r->lsi_keywords ? count($r->lsi_keywords) : '-' }}</td>
                            <td class="p-3 text-sm text-gray-600">{{ $r->entities ? count($r->entities) : '-' }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full
                                    {{ $r->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $r->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $r->status === 'processing' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $r->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}">
                                    {{ $r->status }}
                                </span>
                            </td>
                            <td class="p-3 text-sm text-gray-600">{{ $r->source }}</td>
                            <td class="p-3 text-sm text-gray-600">{{ $r->created_at->diffForHumans() }}</td>
                            <td class="p-3">
                                <a href="{{ route('keywordresearch.show', $r->id) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Detail</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-gray-500">Belum ada riset keyword.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $researches->links() }}</div>
        </div>

        {{-- Tab: API --}}
        <div id="tab-content-api" class="pt-4 space-y-4" style="display:none">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h2 class="text-lg font-bold mb-2">API Connection</h2>
                <p class="text-sm text-gray-500">Gunakan API endpoint terpusat untuk mengakses Keyword Research dan modul lainnya secara terprogram.</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Base URL</p>
                        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200">https://tools.test/api/v1/tool</pre>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Authentication</p>
                        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200">X-API-Key: juki_{your_api_key}</pre>
                        <p class="text-xs text-gray-400 mt-1">Atau gunakan <code class="text-xs bg-gray-200 px-1 rounded">Bearer juki_{your_api_key}</code></p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Example Request</p>
                        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 overflow-x-auto">curl -X POST https://tools.test/api/v1/tool/keyword-research/research \
  -H "X-API-Key: juki_{your_api_key}" \
  -H "Content-Type: application/json" \
  -d '{"keyword": "kopi nusantara", "locale": "id", "lsi_count": 12, "entities_count": 7}'</pre>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Check Status</p>
                        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 overflow-x-auto">curl -X POST https://tools.test/api/v1/tool/keyword-research/status \
  -H "X-API-Key: juki_{your_api_key}" \
  -H "Content-Type: application/json" \
  -d '{"id": 1}'</pre>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-gray-200">
                    <a href="{{ route('api-keys.index') }}" class="inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                        Kelola API Keys
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function switchTab(tab) {
        document.getElementById('tab-content-research').style.display = tab === 'research' ? 'block' : 'none';
        document.getElementById('tab-content-api').style.display = tab === 'api' ? 'block' : 'none';
        document.getElementById('tab-btn-research').className = 'px-5 py-3 text-sm font-medium border-b-2 transition ' + (tab === 'research' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700');
        document.getElementById('tab-btn-api').className = 'px-5 py-3 text-sm font-medium border-b-2 transition ' + (tab === 'api' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700');
    }
    </script>
</div>
@endsection
