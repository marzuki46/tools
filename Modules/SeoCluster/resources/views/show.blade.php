@extends('layouts.app')

@section('title', $cluster->name)

@section('content')
<div class="space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <a href="{{ route('seocluster.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
            <h1 class="text-2xl font-bold mt-1">{{ $cluster->name }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ $cluster->description }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($cluster->status === 'draft' || $cluster->status === 'paused')
                <button onclick="toggleCluster({{ $cluster->id }}, 'activate')"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition">
                    ▶ Aktifkan
                </button>
            @elseif($cluster->status === 'active')
                <button onclick="toggleCluster({{ $cluster->id }}, 'pause')"
                    class="bg-yellow-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-yellow-600 transition">
                    ⏸ Jeda
                </button>
            @endif
            <a href="{{ route('seocluster.edit', $cluster->id) }}"
                class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-100 transition">
                ✏️ Edit
            </a>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm border border-green-200">
            <div class="text-2xl font-bold text-green-600">{{ $progress['published'] }}</div>
            <div class="text-sm text-gray-500">Published</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-yellow-200">
            <div class="text-2xl font-bold text-yellow-600">{{ $progress['pending'] }}</div>
            <div class="text-sm text-gray-500">Pending</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-red-200">
            <div class="text-2xl font-bold text-red-600">{{ $progress['failed'] }}</div>
            <div class="text-sm text-gray-500">Failed</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-blue-200">
            <div class="text-2xl font-bold text-blue-600">{{ $progress['total'] }}</div>
            <div class="text-sm text-gray-500">Total Keyword</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700">Progress Cluster</span>
            <span class="text-sm font-bold text-green-600">{{ $progress['percent'] }}%</span>
        </div>
        <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
            <div class="bg-green-500 h-2.5 rounded-full transition-all" style="width: {{ $progress['percent'] }}%"></div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6 items-start">
        <div class="col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-bold">Daftar Keyword</h3>
                    <button onclick="addKeyword({{ $cluster->id }})"
                        class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-indigo-700 transition">
                        + Tambah
                    </button>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Keyword</th>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">URL</th>
                            <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($keywords as $kw)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="p-3 text-sm font-medium">{{ $kw->keyword }}</td>
                            <td class="p-3">
                                @php
                                    $badgeMap = [
                                        'pending' => 'bg-gray-100 text-gray-600', 'researching' => 'bg-blue-100 text-blue-700',
                                        'researched' => 'bg-indigo-100 text-indigo-700', 'generating' => 'bg-blue-100 text-blue-700',
                                        'content_generated' => 'bg-indigo-100 text-indigo-700', 'publishing' => 'bg-yellow-100 text-yellow-700',
                                        'published' => 'bg-green-100 text-green-700', 'failed' => 'bg-red-100 text-red-700',
                                    ];
                                    $badge = $badgeMap[$kw->status] ?? 'bg-gray-100 text-gray-600';
                                @endphp
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $badge }}">{{ str_replace('_', ' ', $kw->status) }}</span>
                            </td>
                            <td class="p-3">
                                @if($kw->post_url)
                                    <a href="{{ $kw->post_url }}" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800">{{ Str::limit($kw->post_url, 30) }}</a>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="p-3">
                                <form action="{{ route('seocluster.remove-keyword', [$cluster->id, $kw->id]) }}" method="POST"
                                    onsubmit="return confirm('Hapus keyword {{ $kw->keyword }}?')">
                                    @csrf @method('DELETE')
                                    <button class="text-sm text-red-500 hover:text-red-700 font-medium">&times;</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-gray-500">Belum ada keyword</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $keywords->links() }}</div>
        </div>

        <div class="col-span-1 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-bold">Info Cluster</h3>
                </div>
                <div class="p-5 space-y-2.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Parent Keyword</span>
                        <span class="font-medium text-gray-800">{{ $cluster->parent_keyword }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Status</span>
                        <span class="font-medium text-gray-800">{{ ucfirst($cluster->status) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Schedule</span>
                        <span class="font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $cluster->schedule)) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Gambar</span>
                        <span class="font-medium text-gray-800">{{ $cluster->image_keyword }} ({{ $cluster->image_source }})</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">WebP Quality</span>
                        <span class="font-medium text-gray-800">{{ $cluster->webp_quality }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Dibuat</span>
                        <span class="font-medium text-gray-800">{{ $cluster->created_at->format('d M Y H:i') }}</span>
                    </div>
                </div>
            </div>

            @if($logs->count())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-bold">Log Terakhir</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($logs as $log)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <span class="text-sm text-gray-700">{{ $log->action }}</span>
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full
                            {{ $log->status === 'completed' ? 'bg-green-100 text-green-700' : ($log->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ $log->status }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
function toggleCluster(id, action) {
    fetch(`/keyword-clusters/${id}/${action}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
}

function addKeyword(clusterId) {
    const keyword = prompt('Masukkan keyword baru:');
    if (!keyword) return;
    fetch(`/keyword-clusters/${clusterId}/keywords`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ keyword })
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
}
</script>
@endsection
