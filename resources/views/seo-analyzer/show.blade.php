@extends('layouts.app')

@section('title', 'SEO Analysis: ' . $analysis->url)

@section('content')
@php $r = $analysis->result; @endphp

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('seo-analyzer.index') }}" class="text-gray-400 hover:text-gray-600">&larr; Kembali</a>
            <h1 class="text-2xl font-bold truncate max-w-lg">{{ $analysis->url }}</h1>
        </div>
        <form action="{{ route('seo-analyzer.destroy', $analysis->id) }}" method="POST" onsubmit="return confirm('Hapus analisis ini?')">
            @csrf @method('DELETE')
            <button class="text-red-500 hover:text-red-700 text-sm">Hapus</button>
        </form>
    </div>

    {{-- Score Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center gap-6">
            <div class="flex-shrink-0 w-28 h-28 rounded-full {{ $analysis->gradeBg() }} flex items-center justify-center">
                <span class="text-4xl font-bold {{ $analysis->gradeColor() }}">{{ $analysis->grade() }}</span>
            </div>
            <div>
                <p class="text-4xl font-bold {{ $analysis->gradeColor() }}">{{ $analysis->score }}/100</p>
                <p class="text-sm text-gray-500 mt-1">
                    @if ($analysis->score >= 90) Sangat Baik 🎉
                    @elseif ($analysis->score >= 75) Baik 👍
                    @elseif ($analysis->score >= 55) Cukup
                    @elseif ($analysis->score >= 35) Kurang
                    @else Buruk ❌
                    @endif
                </p>
                @if ($analysis->keyword)
                <p class="text-xs text-gray-400 mt-1">Keyword: <strong>{{ $analysis->keyword }}</strong></p>
                @endif
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col justify-center">
            <p class="text-xs text-gray-400 uppercase font-semibold">Title</p>
            <p class="text-sm font-medium mt-1 truncate">{{ $r['title'] ?: '-' }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col justify-center">
            <p class="text-xs text-gray-400 uppercase font-semibold">Meta Description</p>
            <p class="text-sm mt-1 truncate">{{ $r['meta_description'] ?: '-' }}</p>
        </div>
    </div>

    {{-- Error --}}
    @if (!empty($r['error']))
    <div class="bg-red-50 border border-red-200 p-6 rounded-xl text-center text-red-700">
        <p class="font-medium">{{ $r['error'] }}</p>
    </div>
    @else

    {{-- Detail Checks --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($r['checks'] as $key => $check)
        @php
            $labels = [
                'title' => ['Tag Title', 'Pastikan title mengandung keyword, 30-60 karakter'],
                'meta_description' => ['Meta Description', 'Deskripsi 120-165 karakter dengan keyword'],
                'h1' => ['Tag H1', 'Satu H1 mengandung keyword utama'],
                'headings' => ['Struktur Heading (H2-H4)', 'Heading hierarkis untuk readability'],
                'content_length' => ['Panjang Konten', 'Minimal 800 kata, ideal 1500+'],
                'images' => ['Optimasi Gambar', 'Semua gambar harus punya alt text'],
                'links' => ['Internal & External Link', 'Internal link 3+ dan external link 1+'],
                'og_tags' => ['Open Graph Tags', 'og:title, og:description, og:image untuk sosial media'],
                'canonical' => ['Tag Canonical', 'Cegah duplicate content'],
                'robots' => ['Meta Robots', 'Pastikan halaman tidak noindex'],
            ];
            $label = $labels[$key] ?? [$key, ''];
            $pct = $check['max'] > 0 ? round(($check['score'] / $check['max']) * 100) : 0;
            $barColor = $pct >= 80 ? 'bg-green-500' : ($pct >= 40 ? 'bg-yellow-500' : 'bg-red-500');
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-semibold text-sm">{{ $label[0] }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $label[1] }}</p>
                </div>
                <span class="text-lg font-bold {{ $pct >= 80 ? 'text-green-600' : ($pct >= 40 ? 'text-yellow-500' : 'text-red-500') }}">{{ $check['score'] }}/{{ $check['max'] }}</span>
            </div>

            {{-- Bar --}}
            <div class="w-full h-2 bg-gray-100 rounded-full mb-3">
                <div class="h-2 rounded-full {{ $barColor }} transition-all" style="width: {{ $pct }}%"></div>
            </div>

            {{-- Value --}}
            @if ($key === 'title' && $check['found'])
            <div class="bg-gray-50 p-2 rounded text-xs font-mono mb-2 truncate">"{{ $check['found'] }}"</div>
            @endif
            @if ($key === 'meta_description' && $check['found'])
            <div class="bg-gray-50 p-2 rounded text-xs font-mono mb-2 truncate">{{ $check['found'] }}</div>
            @endif
            @if ($key === 'h1' && $check['texts'])
            <div class="text-xs text-gray-600 mb-2">
                @foreach ($check['texts'] as $t)
                <div class="truncate">H1: {{ $t }}</div>
                @endforeach
            </div>
            @endif
            @if ($key === 'content_length')
            <p class="text-xs text-gray-600 mb-2">{{ number_format($check['found']) }} kata</p>
            @endif
            @if ($key === 'images')
            <p class="text-xs text-gray-600 mb-2">{{ $check['found']['total'] }} gambar ({{ $check['found']['with_alt'] }} dengan alt)</p>
            @endif
            @if ($key === 'links')
            <p class="text-xs text-gray-600 mb-2">{{ $check['found']['total'] }} link ({{ $check['found']['internal'] }} internal, {{ $check['found']['external'] }} external)</p>
            @endif
            @if ($key === 'og_tags')
            <div class="text-xs text-gray-600 mb-2">
                @foreach ($check['found'] as $k => $v)
                <div class="truncate">{{ $k }}: {{ $v ?: '❌' }}</div>
                @endforeach
            </div>
            @endif
            @if ($key === 'canonical')
            <p class="text-xs text-gray-600 mb-2 truncate">{{ $check['found'] ?: 'Tidak ada' }}</p>
            @endif
            @if ($key === 'robots')
            <p class="text-xs text-gray-600 mb-2">{{ $check['found'] ?: 'Tidak ada (default: index, follow)' }}</p>
            @endif
            @if ($key === 'headings')
            <div class="text-xs text-gray-600 mb-2">
                @foreach ($check['found'] as $tag => $items)
                @if (count($items) > 0)
                <div>{{ strtoupper($tag) }}: {{ count($items) }}x</div>
                @endif
                @endforeach
            </div>
            @endif

            {{-- Issues --}}
            @if (!empty($check['issues']))
            <div class="mt-2 space-y-1">
                @foreach ($check['issues'] as $issue)
                <p class="text-xs">{!! $issue !!}</p>
                @endforeach
            </div>
            @elseif ($check['score'] > 0)
            <p class="text-xs text-green-600 mt-2">✅ Tidak ada masalah</p>
            @endif
        </div>
        @endforeach
    </div>

    @endif
</div>
@endsection
