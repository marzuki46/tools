@extends('layouts.app')

@section('title', 'Generation #' . $generation->id)

@section('content')
<div class="space-y-6">

    {{-- Breadcrumb + Header --}}
    <div>
        <a href="{{ route('metaadsimagegenerator.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-indigo-600 transition mb-3">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to List
        </a>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ $generation->input_form['product_name'] ?? 'Generation #' . $generation->id }}
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $generation->project->name ?? 'No Project' }}
                    @if ($generation->project->brandKit)
                        <span class="text-gray-300 mx-1">·</span>
                        {{ $generation->project->brandKit->name }}
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if ($generation->status === 'done' && $generation->exports->count() > 0)
                    <a href="{{ route('metaadsimagegenerator.exports.zip', $generation) }}"
                       class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-indigo-700 transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download ZIP
                    </a>
                @endif
                @if ($generation->status === 'done' || $generation->status === 'failed')
                    <form method="POST" action="{{ route('metaadsimagegenerator.regenerate', $generation->id) }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 border border-amber-300 rounded-xl text-sm font-medium text-amber-700 hover:bg-amber-50 transition bg-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Regenerate
                        </button>
                    </form>
                @endif
                <a href="{{ route('metaadsimagegenerator.edit', $generation->id) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-600 transition bg-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </a>
                <form method="POST" action="{{ route('metaadsimagegenerator.destroy', $generation->id) }}"
                      onsubmit="return confirm('Hapus generasi ini?')" class="inline">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 border border-red-200 rounded-xl text-sm font-medium text-red-600 hover:bg-red-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Status Banner --}}
    @if ($generation->status === 'queued' || $generation->status === 'processing')
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <div class="animate-spin w-6 h-6 border-3 border-amber-300 border-t-amber-600 rounded-full"></div>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-amber-900">Sedang Diproses</p>
                    <p class="text-sm text-amber-700 mt-0.5">Iklan kamu sedang dibuat oleh AI. Tunggu beberapa saat.</p>
                </div>
                <button onclick="location.reload()"
                        class="px-4 py-2 bg-amber-100 text-amber-800 rounded-xl text-sm font-medium hover:bg-amber-200 transition">
                    Refresh
                </button>
            </div>
        </div>
    @elseif ($generation->status === 'failed')
        <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-2xl p-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-red-900">Gagal Diproses</p>
                    <p class="text-sm text-red-700 mt-0.5">Terjadi kesalahan. Silakan coba lagi.</p>
                </div>
                <form method="POST" action="{{ route('metaadsimagegenerator.regenerate', $generation->id) }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 bg-red-100 text-red-800 rounded-xl text-sm font-medium hover:bg-red-200 transition">
                        Coba Ulang
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- Main Content: Exports --}}
    @if ($generation->exports->count() > 0)
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Hasil Iklan</h2>
                <span class="text-sm text-gray-400">{{ $generation->exports->count() }} ukuran</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach ($generation->exports as $export)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md transition">
                        {{-- Image --}}
                        <div class="bg-gray-50 flex items-center justify-center p-4 min-h-[280px]">
                            @if ($export->final_image_path)
                                <img src="{{ Storage::url($export->final_image_path) }}"
                                     alt="{{ $export->placement }}"
                                     class="max-w-full max-h-80 rounded-lg shadow-sm"
                                     loading="lazy">
                            @else
                                <div class="text-gray-300 text-sm">No image</div>
                            @endif
                        </div>
                        {{-- Info Bar --}}
                        <div class="px-5 py-3.5 border-t border-gray-100 flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-sm text-gray-900">{{ $export->placement }}</span>
                                <span class="text-xs text-gray-400 ml-2">{{ $export->width }}×{{ $export->height }}px</span>
                            </div>
                            <a href="{{ route('metaadsimagegenerator.exports.download', $export) }}"
                               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-semibold hover:bg-indigo-100 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($generation->status === 'done')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <p class="text-gray-500 text-sm">Tidak ada export tersedia</p>
        </div>
    @endif

    {{-- Sidebar Info --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Reference Images + Input Details --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Reference Images --}}
            @if ($generation->modelAsset || $generation->asset)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Referensi Gambar</h3>
                    <div class="grid grid-cols-2 gap-4">
                        @if ($generation->modelAsset)
                            <div>
                                <span class="text-xs text-gray-400 uppercase tracking-wide font-medium">Model / Wajah</span>
                                <a href="{{ Storage::url($generation->modelAsset->file_path) }}" target="_blank" class="block mt-1.5">
                                    <img src="{{ Storage::url($generation->modelAsset->file_path) }}"
                                         alt="Model"
                                         class="w-full aspect-[4/3] object-cover rounded-xl border border-gray-200 hover:opacity-80 transition">
                                </a>
                                <p class="text-xs text-gray-400 mt-1.5 truncate">{{ $generation->modelAsset->original_name }}</p>
                            </div>
                        @endif
                        @if ($generation->asset)
                            <div>
                                <span class="text-xs text-gray-400 uppercase tracking-wide font-medium">Produk</span>
                                <a href="{{ Storage::url($generation->asset->file_path) }}" target="_blank" class="block mt-1.5">
                                    <img src="{{ Storage::url($generation->asset->file_path) }}"
                                         alt="Product"
                                         class="w-full aspect-[4/3] object-cover rounded-xl border border-gray-200 hover:opacity-80 transition">
                                </a>
                                <p class="text-xs text-gray-400 mt-1.5 truncate">{{ $generation->asset->original_name }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Input Details --}}
            @if ($generation->input_form)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Detail Iklan</h3>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4">
                        @foreach ([
                            'product_name' => ['Produk', 'text-gray-900 font-medium'],
                            'headline' => ['Headline', 'text-gray-800'],
                            'sub_headline' => ['Sub-headline', 'text-gray-800'],
                            'cta' => ['CTA', 'text-gray-800'],
                            'vibe' => ['Vibe', 'text-gray-600'],
                            'target_audience' => ['Target', 'text-gray-600'],
                        ] as $key => [$label, $class])
                            @if (!empty($generation->input_form[$key]))
                                <div>
                                    <dt class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">{{ $label }}</dt>
                                    <dd class="text-sm {{ $class }}">{{ $generation->input_form[$key] }}</dd>
                                </div>
                            @endif
                        @endforeach
                        @if (!empty($generation->input_form['sizes']))
                            <div>
                                <dt class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Sizes</dt>
                                <dd class="flex flex-wrap gap-1.5 mt-1">
                                    @foreach ($generation->input_form['sizes'] as $size)
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-mono">{{ $size }}</span>
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                        @if (!empty($generation->input_form['notes']))
                            <div class="col-span-2">
                                <dt class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Catatan</dt>
                                <dd class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3 mt-1">{{ $generation->input_form['notes'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>

        {{-- Right: Meta Info --}}
        <div class="space-y-5">

            {{-- Status --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Status</h3>
                @php
                    $statusConfig = match($generation->status) {
                        'done' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'dot' => 'bg-green-500', 'label' => 'Selesai'],
                        'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'dot' => 'bg-red-500', 'label' => 'Gagal'],
                        'processing' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500', 'label' => 'Diproses'],
                        default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'dot' => 'bg-gray-400', 'label' => 'Antrian'],
                    };
                @endphp
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full {{ $statusConfig['dot'] }}"></span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }}">
                        {{ $statusConfig['label'] }}
                    </span>
                </div>
                <div class="mt-3 space-y-1">
                    <p class="text-xs text-gray-400">Dibuat: {{ $generation->created_at->diffForHumans() }}</p>
                    @if ($generation->credit_used)
                        <p class="text-xs text-gray-400">{{ $generation->credit_used }} kredit terpakai</p>
                    @endif
                </div>
            </div>

            {{-- AI Provider --}}
            @if ($generation->ai_provider)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">AI Provider</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-400">Provider</span>
                            <span class="text-sm font-mono text-gray-700">{{ $generation->ai_provider }}</span>
                        </div>
                        @if ($generation->ai_model)
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Model</span>
                                <span class="text-sm font-mono text-gray-700">{{ $generation->ai_model }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Compiled Prompt --}}
            @if ($generation->compiled_prompt)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Prompt</h3>
                    <p class="text-xs text-gray-600 bg-gray-50 rounded-xl p-3 leading-relaxed max-h-48 overflow-y-auto">{{ $generation->compiled_prompt }}</p>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
