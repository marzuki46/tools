@extends('layouts.app')

@section('title', 'Keyword Clusters')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Keyword Clusters</h1>
            <p class="text-gray-500 text-sm mt-1">Kelola cluster keyword untuk konten terstruktur</p>
        </div>
        <a href="{{ route('seocluster.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + Buat Cluster
        </a>
    </div>

    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-sm text-gray-500">Total Cluster</div>
            <div class="text-2xl font-bold">{{ $stats['total'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-sm text-gray-500">Aktif</div>
            <div class="text-2xl font-bold text-green-600">{{ $stats['active'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-sm text-gray-500">Published</div>
            <div class="text-2xl font-bold text-blue-600">{{ $stats['total_published'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-sm text-gray-500">Antrian</div>
            <div class="text-2xl font-bold text-yellow-600">{{ $stats['queue_pending'] }}</div>
            @if($stats['queue_failed'] > 0)
                <div class="text-xs text-red-600 font-medium mt-1">{{ $stats['queue_failed'] }} gagal</div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Nama</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Parent Keyword</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Progress</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Schedule</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clusters as $cluster)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="p-3 font-medium">
                        <a href="{{ route('seocluster.show', $cluster->id) }}" class="text-indigo-600 hover:text-indigo-800">{{ $cluster->name }}</a>
                    </td>
                    <td class="p-3 text-sm text-gray-600">{{ $cluster->parent_keyword }}</td>
                    <td class="p-3">
                        @php
                            $badge = [
                                'draft' => 'bg-gray-100 text-gray-600',
                                'active' => 'bg-green-100 text-green-700',
                                'paused' => 'bg-yellow-100 text-yellow-700',
                                'completed' => 'bg-blue-100 text-blue-700',
                            ][$cluster->status] ?? 'bg-gray-100 text-gray-600';
                        @endphp
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $badge }}">{{ ucfirst($cluster->status) }}</span>
                    </td>
                    <td class="p-3">
                        @php $p = $cluster->progress(); @endphp
                        <div class="flex items-center gap-2">
                            <div class="w-24 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ $p['percent'] }}%"></div>
                            </div>
                            <span class="text-xs text-gray-500">{{ $p['published'] }}/{{ $p['total'] }}</span>
                        </div>
                    </td>
                    <td class="p-3 text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $cluster->schedule)) }}</td>
                    <td class="p-3">
                        <a href="{{ route('seocluster.edit', $cluster->id) }}" class="inline-block px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition">Edit</a>
                        <form action="{{ route('seocluster.destroy', $cluster->id) }}" method="POST" class="inline-block"
                            onsubmit="return confirm('Hapus cluster ini? Semua keyword akan ikut terhapus.')">
                            @csrf @method('DELETE')
                            <button class="px-3 py-1.5 text-xs font-medium rounded-lg border border-red-300 text-red-600 hover:bg-red-50 transition">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="p-8 text-center text-gray-500">
                        Belum ada cluster.
                        <a href="{{ route('seocluster.create') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Buat cluster pertama</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $clusters->links() }}</div>
</div>
@endsection
