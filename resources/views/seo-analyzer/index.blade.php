@extends('layouts.app')

@section('title', 'SEO Analyzer')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">SEO Analyzer</h1>
            <p class="text-gray-500 text-sm mt-1">Analisis on-page SEO gratis — cukup masukkan URL halaman</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <form action="{{ route('seo-analyzer.analyze') }}" method="POST" class="space-y-4">
            @csrf
            <div class="flex gap-3 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL Halaman *</label>
                    <input type="url" name="url" required placeholder="https://example.com/artikel"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="w-64">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Target Keyword <span class="text-gray-400 font-normal">(opsional)</span></label>
                    <input type="text" name="keyword" placeholder="Contoh: digital marketing"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition shrink-0">
                    🔍 Analisis
                </button>
            </div>
        </form>
    </div>

    @if ($analyses->count() > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">URL</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Keyword</th>
                    <th class="text-center p-3 text-xs font-semibold text-gray-500 uppercase">Skor</th>
                    <th class="text-center p-3 text-xs font-semibold text-gray-500 uppercase">Grade</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($analyses as $a)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="p-3 text-sm max-w-xs truncate">
                        <a href="{{ route('seo-analyzer.show', $a->id) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ $a->url }}</a>
                    </td>
                    <td class="p-3 text-sm text-gray-600">{{ $a->keyword ?: '-' }}</td>
                    <td class="p-3 text-center font-bold text-lg {{ $a->gradeColor() }}">{{ $a->score }}</td>
                    <td class="p-3 text-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold {{ $a->gradeBg() }} {{ $a->gradeColor() }}">
                            {{ $a->grade() }}
                        </span>
                    </td>
                    <td class="p-3 text-sm text-gray-600">{{ $a->created_at->diffForHumans() }}</td>
                    <td class="p-3">
                        <form action="{{ route('seo-analyzer.destroy', $a->id) }}" method="POST" onsubmit="return confirm('Hapus analisis ini?')">
                            @csrf @method('DELETE')
                            <button class="text-red-500 hover:text-red-700 text-xs">Hapus</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div>{{ $analyses->links() }}</div>
    @else
    <div class="bg-gray-50 p-10 rounded-xl text-center text-gray-500">
        <p class="text-lg mb-1">Belum ada analisis</p>
        <p class="text-sm">Masukkan URL di atas untuk memulai analisis SEO on-page.</p>
    </div>
    @endif
</div>
@endsection
