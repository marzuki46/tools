@extends('layouts.app')

@section('title', $research->target_keyword)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('keywordresearch.index') }}" class="text-gray-400 hover:text-gray-600">&larr; Kembali</a>
            <h1 class="text-2xl font-bold">{{ $research->target_keyword }}</h1>
            @if ($research->status === 'completed')
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Selesai</span>
            @elseif ($research->status === 'failed')
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">Gagal</span>
            @elseif ($research->status === 'processing')
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700">Processing</span>
            @else
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-700">Pending</span>
            @endif
        </div>
        <div class="flex gap-2">
            @if ($research->status === 'failed')
            <form action="{{ route('keywordresearch.retry', $research->id) }}" method="POST">
                @csrf
                <button class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-yellow-700 transition">Ulang</button>
            </form>
            @endif
            <form action="{{ route('keywordresearch.destroy', $research->id) }}" method="POST" onsubmit="return confirm('Hapus riset ini?')">
                @csrf
                @method('DELETE')
                <button class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition">Hapus</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-xs text-gray-500 uppercase font-semibold">Status</div>
            <div class="font-semibold mt-1 capitalize">{{ $research->status }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-xs text-gray-500 uppercase font-semibold">Sumber</div>
            <div class="font-semibold mt-1 capitalize">{{ $research->source }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-xs text-gray-500 uppercase font-semibold">Dibuat</div>
            <div class="font-semibold mt-1">{{ $research->created_at->format('d M Y H:i') }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-xs text-gray-500 uppercase font-semibold">Locale</div>
            <div class="font-semibold mt-1">{{ $research->locale }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-xs text-gray-500 uppercase font-semibold">LSI Diminta</div>
            <div class="font-semibold mt-1">{{ $research->lsi_count ?? 12 }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-xs text-gray-500 uppercase semibold">Entities Diminta</div>
            <div class="font-semibold mt-1">{{ $research->entities_count ?? 7 }}</div>
        </div>
    </div>

    @if ($research->status === 'processing' || $research->status === 'pending')
    <div class="bg-yellow-50 border border-yellow-200 p-6 rounded-xl text-center">
        <p class="text-yellow-700 font-medium">Riset sedang diproses...</p>
        <p class="text-yellow-600 text-sm mt-1">Halaman akan otomatis refresh.</p>
    </div>
    <meta http-equiv="refresh" content="5">
    @endif

    @if ($research->lsi_keywords)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-bold mb-4">LSI Keywords ({{ count($research->lsi_keywords) }})</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach ($research->lsi_keywords as $lsi)
            <div class="border border-gray-200 rounded-lg p-3">
                <div class="font-medium">{{ $lsi['keyword'] }}</div>
                <div class="text-sm text-gray-500 mt-1">
                    Volume: <span class="font-semibold text-gray-700">{{ $lsi['search_volume'] ?? 'N/A' }}</span>
                    &middot;
                    Relevansi: <span class="font-semibold text-gray-700">{{ isset($lsi['relevance']) ? number_format($lsi['relevance'] * 100, 0) . '%' : 'N/A' }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if ($research->entities)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-bold mb-4">Entities ({{ count($research->entities) }})</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach ($research->entities as $entity)
            <div class="border border-gray-200 rounded-lg p-3">
                <div class="flex items-center gap-2">
                    <span class="font-medium">{{ $entity['name'] }}</span>
                    <span class="bg-gray-100 px-2 py-0.5 rounded text-xs text-gray-600">{{ $entity['type'] ?? 'N/A' }}</span>
                    @if (isset($entity['relevance']))
                    <span class="text-xs text-gray-400 ml-auto">{{ number_format($entity['relevance'] * 100, 0) }}% relevan</span>
                    @endif
                </div>
                @if (!empty($entity['mention']))
                <div class="text-sm text-gray-600 mt-1">{{ $entity['mention'] }}</div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if ($research->status === 'failed' && $research->raw_response)
    <div class="bg-red-50 border border-red-200 p-4 rounded-xl">
        <h3 class="font-bold text-red-700">Error Detail</h3>
        <pre class="text-sm text-red-600 mt-1 overflow-auto">{{ json_encode($research->raw_response, JSON_PRETTY_PRINT) }}</pre>
    </div>
    @endif

    @if ($research->webhook_url)
    <div class="bg-gray-50 border border-gray-200 p-4 rounded-xl">
        <h3 class="font-semibold text-sm text-gray-700">Webhook</h3>
        <p class="text-sm text-gray-500 mt-1">URL: <code class="text-xs bg-gray-200 px-1 rounded">{{ $research->webhook_url }}</code></p>
        @if ($research->webhook_sent_at)
        <p class="text-sm text-green-600 mt-1">Dikirim: {{ $research->webhook_sent_at->diffForHumans() }}</p>
        @endif
    </div>
    @endif

    <div class="bg-gray-50 border border-gray-200 p-4 rounded-xl">
        <h3 class="font-semibold text-sm text-gray-700">API</h3>
        <p class="text-xs text-gray-500 mt-1">Akses hasil ini via API terpusat:</p>
        <pre class="bg-white p-3 rounded-lg text-xs font-mono border border-gray-200 mt-2 overflow-x-auto">curl -X POST https://tools.test/api/v1/tool/keyword-research/status \
  -H "X-API-Key: juki_{your_api_key}" \
  -H "Content-Type: application/json" \
  -d '{"id": {{ $research->id }}}'</pre>
    </div>
</div>
@endsection
