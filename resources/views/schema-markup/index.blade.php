@extends('layouts.app')

@section('title', 'Schema Markup Generator')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Schema Markup Generator</h1>
            <p class="text-gray-500 text-sm mt-1">Buat JSON-LD schema.org untuk berbagai tipe konten</p>
        </div>
        <a href="{{ route('schema-markup.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + Baru
        </a>
    </div>

    @if ($markups->count() > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Nama</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Tipe</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">URL Target</th>
                    <th class="text-center p-3 text-xs font-semibold text-gray-500 uppercase">AI</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($markups as $m)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="p-3 text-sm font-medium">
                        <a href="{{ route('schema-markup.show', $m->id) }}" class="text-indigo-600 hover:text-indigo-800">{{ $m->name ?: 'Tanpa Nama' }}</a>
                    </td>
                    <td class="p-3 text-sm">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                            {{ $m->getTypeLabel() }}
                        </span>
                    </td>
                    <td class="p-3 text-sm text-gray-600 max-w-xs truncate">{{ $m->target_url ?: '-' }}</td>
                    <td class="p-3 text-center text-sm">
                        @if ($m->use_ai)
                            <span class="text-purple-600">🤖 Ya</span>
                        @else
                            <span class="text-gray-400">Manual</span>
                        @endif
                    </td>
                    <td class="p-3 text-sm text-gray-600">{{ $m->created_at->diffForHumans() }}</td>
                    <td class="p-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('schema-markup.show', $m->id) }}" class="text-indigo-600 hover:text-indigo-800 text-xs">Lihat</a>
                            <form action="{{ route('schema-markup.destroy', $m->id) }}" method="POST" onsubmit="return confirm('Hapus schema markup ini?')">
                                @csrf @method('DELETE')
                                <button class="text-red-500 hover:text-red-700 text-xs">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div>{{ $markups->links() }}</div>
    @else
    <div class="bg-gray-50 p-10 rounded-xl text-center text-gray-500">
        <p class="text-lg mb-1">Belum ada schema markup</p>
        <p class="text-sm">Klik "Baru" untuk membuat schema markup pertama dari konten yang sudah ada.</p>
    </div>
    @endif
</div>
@endsection
