@extends('layouts.app')

@section('title', 'Content Generator')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Content Generator</h1>
            <p class="text-gray-500 text-sm mt-1">Buat konten AI dalam 3 fase: artikel, pertanyaan kritis, konten final</p>
        </div>
        <a href="{{ route('contentgenerator.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + Konten Baru
        </a>
    </div>

    {{-- Dashboard Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        {{-- Queue Worker Status --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 col-span-2 lg:col-span-2" id="queue-status-card">
            <div class="flex items-center gap-3 h-full">
                <div class="flex-shrink-0 w-14 h-14 rounded-full flex items-center justify-center text-2xl font-bold
                    {{ $queueStatus['color'] === 'green' ? 'bg-green-100 text-green-600' : '' }}
                    {{ $queueStatus['color'] === 'yellow' ? 'bg-yellow-100 text-yellow-600' : '' }}
                    {{ $queueStatus['color'] === 'red' ? 'bg-red-100 text-red-600' : '' }}">
                    <span class="w-4 h-4 rounded-full inline-block
                        {{ $queueStatus['color'] === 'green' ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]' : '' }}
                        {{ $queueStatus['color'] === 'yellow' ? 'bg-yellow-500 shadow-[0_0_8px_rgba(234,179,8,0.6)]' : '' }}
                        {{ $queueStatus['color'] === 'red' ? 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.6)]' : 'bg-gray-300' }}"></span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Queue Worker</p>
                    <p class="text-lg font-bold truncate
                        {{ $queueStatus['color'] === 'green' ? 'text-green-600' : '' }}
                        {{ $queueStatus['color'] === 'yellow' ? 'text-yellow-600' : '' }}
                        {{ $queueStatus['color'] === 'red' ? 'text-red-600' : '' }}">
                        {{ $queueStatus['label'] }}
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        @if ($queueStatus['lastBeat'])
                            Detak terakhir: {{ $queueStatus['lastBeat']->diffForHumans() }}
                        @else
                            Belum pernah terdeteksi
                        @endif
                    </p>
                    <div class="flex gap-2 mt-2">
                        <button onclick="restartWorker()" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2.5 py-1 rounded transition">
                            🔄 Restart
                        </button>
                        <a href="{{ route('contentgenerator.create') }}" class="text-xs bg-indigo-100 hover:bg-indigo-200 text-indigo-700 px-2.5 py-1 rounded transition">
                            ▶ Jalankan
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Requests --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Total Rekues</p>
            <p class="text-2xl font-bold mt-1">{{ number_format($stats['total']) }}</p>
        </div>

        {{-- Completed --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Berhasil</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($stats['completed']) }}</p>
        </div>

        {{-- Pending --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Antrian Proses</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($stats['pending']) }}</p>
            @if ($stats['queue_pending'] > 0)
                <p class="text-xs text-orange-500 mt-0.5">{{ number_format($stats['queue_pending']) }} job di queue</p>
            @endif
        </div>

        {{-- Failed --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Gagal</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($stats['failed']) }}</p>
            @if ($stats['queue_failed'] > 0)
                <p class="text-xs text-red-500 mt-0.5">{{ number_format($stats['queue_failed']) }} job gagal</p>
                <button onclick="retryFailed()" class="text-xs bg-red-100 hover:bg-red-200 text-red-700 px-2.5 py-1 rounded mt-1 transition">
                    🔄 Retry Semua
                </button>
            @elseif ($stats['failed'] > 0)
                <p class="text-xs text-gray-400 mt-0.5">sudah di-retry</p>
            @endif
        </div>

        {{-- Active Users --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">User Aktif</p>
            <p class="text-2xl font-bold mt-1">{{ number_format($stats['active_users']) }}</p>
        </div>
    </div>

    {{-- Queue status detail bar --}}
    <div id="queue-detail" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <p class="text-sm text-gray-600" id="queue-detail-text">Memuat...</p>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Keyword</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Website</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Fase</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Tone</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($generations as $g)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="p-3 font-medium">{{ $g->target_keyword }}</td>
                    <td class="p-3 text-sm text-gray-600">
                        @if ($g->apiKeyWebsite && $g->apiKeyWebsite->domain)
                            <a href="{{ \Illuminate\Support\Str::startsWith($g->apiKeyWebsite->domain, 'http') ? $g->apiKeyWebsite->domain : 'https://'.$g->apiKeyWebsite->domain }}"
                               target="_blank"
                               class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                {{ $g->apiKeyWebsite->site_name ?: $g->apiKeyWebsite->domain }}
                            </a>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="p-3 text-sm text-gray-600">Fase {{ $g->current_phase }}</td>
                    <td class="p-3">
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full
                            {{ $g->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $g->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $g->status === 'phase_1' || $g->status === 'phase_2' || $g->status === 'phase_3' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $g->status === 'draft' ? 'bg-yellow-100 text-yellow-700' : '' }}">
                            {{ $g->status === 'completed' ? 'Selesai' : $g->status }}
                        </span>
                    </td>
                    <td class="p-3 text-sm text-gray-600 capitalize">{{ $g->tone }}</td>
                    <td class="p-3 text-sm text-gray-600">{{ $g->created_at->diffForHumans() }}</td>
                    <td class="p-3">
                        <a href="{{ route('contentgenerator.show', $g->id) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Detail</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="p-6 text-center text-gray-500">Belum ada konten.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $generations->links() }}</div>
</div>

@push('scripts')
<script>
function restartWorker() {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '⏳ Restart...';
    fetch('{{ route('contentgenerator.queue-restart') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
        .then(r => r.json())
        .then(d => {
            document.getElementById('queue-detail').classList.remove('hidden');
            document.getElementById('queue-detail-text').textContent = d.message;
            setTimeout(() => location.reload(), 2000);
        })
        .catch(() => {
            document.getElementById('queue-detail').classList.remove('hidden');
            document.getElementById('queue-detail-text').textContent = 'Gagal merestart worker. Jalankan manual: php artisan queue:work --timeout=240';
        })
        .finally(() => { btn.disabled = false; btn.textContent = '🔄 Restart'; });
}

function retryFailed() {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '⏳ Me-retry...';
    fetch('{{ route('contentgenerator.retry-failed') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
        .then(r => r.json())
        .then(d => {
            document.getElementById('queue-detail').classList.remove('hidden');
            document.getElementById('queue-detail-text').textContent = d.message;
            setTimeout(() => location.reload(), 2000);
        })
        .catch(() => {
            document.getElementById('queue-detail').classList.remove('hidden');
            document.getElementById('queue-detail-text').textContent = 'Gagal me-retry. Jalankan manual: php artisan queue:retry all';
        })
        .finally(() => { btn.disabled = false; btn.textContent = '🔄 Retry Semua'; });
}

setInterval(() => {
    fetch('{{ route('contentgenerator.queue-status') }}')
        .then(r => r.json())
        .then(d => {
            const card = document.getElementById('queue-status-card');
            const dot = card.querySelector('span.w-4.h-4');
            const label = card.querySelector('p.text-lg.font-bold');
            const colors = { green: ['bg-green-500', 'text-green-600', 'shadow-[0_0_8px_rgba(34,197,94,0.6)]'], yellow: ['bg-yellow-500', 'text-yellow-600', 'shadow-[0_0_8px_rgba(234,179,8,0.6)]'], red: ['bg-red-500', 'text-red-600', 'shadow-[0_0_8px_rgba(239,68,68,0.6)]'] };
            const c = colors[d.color];
            if (c) {
                dot.className = 'w-4 h-4 rounded-full inline-block ' + c[0] + ' ' + c[2];
                label.className = 'text-lg font-bold truncate ' + c[1];
                label.textContent = d.label;
            }
        });
}, 10000);
</script>
@endpush
@endsection