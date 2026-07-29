@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="text-gray-500 text-sm mt-1">Welcome back, {{ Auth::user()->name }}!</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="text-3xl font-bold text-indigo-600">{{ $stats['websites_count'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Registered Websites</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="text-3xl font-bold text-green-600">{{ $stats['active_website_tools'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Active Tool Licenses</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="text-3xl font-bold text-amber-600">{{ $stats['total_keys'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Total API Keys</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="text-3xl font-bold text-purple-600">{{ $stats['tools_count'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Available Tools</div>
        </div>
    </div>

    {{-- Queue Worker Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" id="queue-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-lg">Queue Worker</h2>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full inline-block
                    {{ $queueStatus['color'] === 'green' ? 'bg-green-500 shadow-[0_0_6px_rgba(34,197,94,0.5)]' : '' }}
                    {{ $queueStatus['color'] === 'yellow' ? 'bg-yellow-500' : '' }}
                    {{ $queueStatus['color'] === 'red' ? 'bg-red-500 shadow-[0_0_6px_rgba(239,68,68,0.5)]' : '' }}
                    {{ !in_array($queueStatus['color'], ['green','yellow','red']) ? 'bg-gray-300' : '' }}"
                    id="queue-dot"></span>
                <span class="text-sm font-medium" id="queue-label">{{ $queueStatus['label'] }}</span>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-xs text-gray-500">Antrian</div>
                <div class="text-xl font-bold" id="queue-pending">{{ $queueStatus['pendingJobs'] }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-xs text-gray-500">Gagal</div>
                <div class="text-xl font-bold text-red-600" id="queue-failed">{{ $queueStatus['failedJobs'] }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-xs text-gray-500">Denyut Jantung</div>
                <div class="text-sm font-medium" id="queue-beat">{{ $queueStatus['lastBeatHuman'] ?? 'Tidak ada' }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-xs text-gray-500">Status</div>
                <div class="text-sm font-medium capitalize" id="queue-status-text">{{ $queueStatus['status'] }}</div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button onclick="queueStart()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition disabled:opacity-50" id="btn-start">
                &#9654; Jalankan Worker
            </button>
            <button onclick="queueRetry()" class="bg-amber-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-600 transition disabled:opacity-50" id="btn-retry">
                &#8635; Retry Failed Jobs
            </button>
            <button onclick="queueClear()" class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-600 transition disabled:opacity-50" id="btn-clear">
                &#10005; Clear Failed
            </button>
        </div>
        <div id="queue-message" class="mt-3 text-sm hidden"></div>
    </div>

    {{-- Quick Actions --}}
    <div>
        <h2 class="font-semibold text-lg mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <a href="{{ route('websites.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:border-green-200 transition group">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mb-3 group-hover:bg-green-200 transition">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                </div>
                <h3 class="font-medium text-gray-900">My Websites</h3>
                <p class="text-sm text-gray-500 mt-1">Register & manage your domains</p>
            </a>
            <a href="{{ route('api-keys.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:border-indigo-200 transition group">
                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mb-3 group-hover:bg-indigo-200 transition">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
                <h3 class="font-medium text-gray-900">Manage API Keys</h3>
                <p class="text-sm text-gray-500 mt-1">Create and manage your API access keys</p>
            </a>

            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('keyword-research'))
            <a href="{{ route('keywordresearch.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:border-blue-200 transition group">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mb-3 group-hover:bg-blue-200 transition">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                </div>
                <h3 class="font-medium text-gray-900">Keyword Research</h3>
                <p class="text-sm text-gray-500 mt-1">AI-powered keyword & entity research</p>
            </a>
            @endif

            @if (\Illuminate\Support\Facades\Auth::user()->hasToolAccess('content-generator'))
            <a href="{{ route('contentgenerator.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:border-orange-200 transition group">
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mb-3 group-hover:bg-orange-200 transition">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
                <h3 class="font-medium text-gray-900">Content Generator</h3>
                <p class="text-sm text-gray-500 mt-1">3-phase AI article generation</p>
            </a>
            @endif

            <a href="{{ route('tools.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:border-gray-200 transition group">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mb-3 group-hover:bg-gray-200 transition">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h3 class="font-medium text-gray-900">Tool Settings</h3>
                <p class="text-sm text-gray-500 mt-1">Manage your tools and integrations</p>
            </a>
        </div>
    </div>

    {{-- Recent API Keys --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold">Recent API Keys</h2>
            <a href="{{ route('api-keys.index') }}" class="text-indigo-600 text-sm hover:underline">View all</a>
        </div>
        @if ($recent_keys->count() > 0)
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-gray-500">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Last Used</th>
                        <th class="px-6 py-3 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($recent_keys as $key)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $key->name }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $isActive = $key->is_active && (!$key->expires_at || $key->expires_at->isFuture());
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $isActive ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never' }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->created_at->format('M d, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No API keys yet</p>
                <a href="{{ route('api-keys.index') }}" class="inline-block mt-2 text-indigo-600 text-sm hover:underline">Create your first key</a>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function queueStart() {
    const btn = document.getElementById('btn-start');
    btn.disabled = true; btn.textContent = '\u23F3 Menjalankan...';
    fetch('{{ route('queue.start') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
        .then(r => r.json()).then(d => showMessage(d.message, d.success ? 'green' : 'red'))
        .finally(() => { btn.disabled = false; btn.textContent = '\u25B6 Jalankan Worker'; setTimeout(pollQueue, 1000); });
}
function queueRetry() {
    const btn = document.getElementById('btn-retry');
    btn.disabled = true; btn.textContent = '\u23F3 Me-retry...';
    fetch('{{ route('queue.retry-failed') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
        .then(r => r.json()).then(d => showMessage(d.message, 'green'))
        .finally(() => { btn.disabled = false; btn.textContent = '\u21BB Retry Failed Jobs'; setTimeout(pollQueue, 2000); });
}
function queueClear() {
    if (!confirm('Hapus semua job gagal?')) return;
    const btn = document.getElementById('btn-clear');
    btn.disabled = true; btn.textContent = '\u23F3 Membersihkan...';
    fetch('{{ route('queue.clear-failed') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
        .then(r => r.json()).then(d => showMessage(d.message, 'green'))
        .finally(() => { btn.disabled = false; btn.textContent = '\u2715 Clear Failed'; setTimeout(pollQueue, 1000); });
}
function showMessage(msg, color) {
    const el = document.getElementById('queue-message');
    el.className = 'mt-3 text-sm p-3 rounded ' + (color === 'green' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700');
    el.innerHTML = msg.replace(/\n/g, '<br>').replace(/<code>(.*?)<\/code>/g, '<code class="bg-gray-200 px-1 rounded text-xs font-mono">$1</code>');
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 15000);
}
function pollQueue() {
    fetch('{{ route('queue.status') }}').then(r => r.json()).then(d => {
        document.getElementById('queue-dot').className = 'w-3 h-3 rounded-full inline-block ' + (
            d.color === 'green' ? 'bg-green-500 shadow-[0_0_6px_rgba(34,197,94,0.5)]' :
            d.color === 'yellow' ? 'bg-yellow-500' :
            d.color === 'red' ? 'bg-red-500 shadow-[0_0_6px_rgba(239,68,68,0.5)]' : 'bg-gray-300');
        document.getElementById('queue-label').textContent = d.label;
        document.getElementById('queue-pending').textContent = d.pendingJobs;
        document.getElementById('queue-failed').textContent = d.failedJobs;
        document.getElementById('queue-beat').textContent = d.lastBeatHuman || 'Tidak ada';
        document.getElementById('queue-status-text').textContent = d.status;
    });
}
setInterval(pollQueue, 10000);
</script>
@endpush
@endsection
