@extends('layouts.app')

@section('title', 'Meta Ads Generator')

@section('content')
<div class="space-y-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Meta Ads Generator</h1>
            <p class="text-gray-500 text-sm mt-1">Buat gambar iklan AI untuk Facebook & Instagram</p>
        </div>
        <a href="{{ route('metaadsimagegenerator.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-indigo-700 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Ad Creative
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
            <div class="text-3xl font-bold text-indigo-600">{{ $stats['total_generations'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wide">Generations</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
            <div class="text-3xl font-bold text-green-600">{{ $stats['completed'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wide">Completed</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
            <div class="text-3xl font-bold text-amber-500">{{ $stats['total_generations'] - $stats['completed'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wide">Pending</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
            <div class="text-3xl font-bold text-purple-600">{{ $stats['total_projects'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wide">Projects</div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('metaadsimagegenerator.brand-kits.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-600 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            Brand Kits
        </a>
        <a href="{{ route('metaadsimagegenerator.presets.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-600 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            Presets
        </a>
    </div>

    {{-- Recent Generations --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Recent Generations</h2>
            @if ($recentGenerations->count() > 0)
                <a href="{{ route('metaadsimagegenerator.index') }}" class="text-sm text-indigo-600 hover:underline">View all</a>
            @endif
        </div>

        @if ($recentGenerations->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($recentGenerations as $gen)
                    @php
                        $statusConfig = match($gen->status) {
                            'done' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'dot' => 'bg-green-500', 'label' => 'Done'],
                            'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'dot' => 'bg-red-500', 'label' => 'Failed'],
                            'processing' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500', 'label' => 'Processing'],
                            default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'dot' => 'bg-gray-400', 'label' => 'Queued'],
                        };
                    @endphp
                    <a href="{{ route('metaadsimagegenerator.show', $gen->id) }}"
                       class="group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:border-indigo-200 transition">
                        {{-- Thumbnail / Status Bar --}}
                        <div class="h-2 {{ $statusConfig['dot'] }}"></div>
                        <div class="p-5">
                            <div class="flex items-start justify-between mb-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                                    {{ $statusConfig['label'] }}
                                </span>
                                <span class="text-xs text-gray-400">{{ $gen->created_at->diffForHumans() }}</span>
                            </div>
                            <h3 class="font-semibold text-gray-900 text-sm group-hover:text-indigo-600 transition">
                                {{ $gen->input_form['product_name'] ?? 'Untitled Generation' }}
                            </h3>
                            @if (!empty($gen->input_form['headline']))
                                <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $gen->input_form['headline'] }}</p>
                            @endif
                            <div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-50">
                                <span class="text-xs text-gray-400">{{ $gen->project->name ?? 'No Project' }}</span>
                                <span class="text-xs text-gray-300">·</span>
                                <span class="text-xs text-gray-400 capitalize">{{ $gen->ai_provider ?? '-' }}</span>
                                @if ($gen->exports->count() > 0)
                                    <span class="text-xs text-gray-300">·</span>
                                    <span class="text-xs text-green-600 font-medium">{{ $gen->exports->count() }} exports</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <p class="text-gray-600 font-medium">Belum ada generasi</p>
                <p class="text-sm text-gray-400 mt-1">Mulai buat iklan pertamamu</p>
                <a href="{{ route('metaadsimagegenerator.create') }}"
                   class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Buat Sekarang
                </a>
            </div>
        @endif
    </div>

    {{-- Projects --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Projects</h2>
        </div>

        @if ($projects->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($projects as $project)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:border-indigo-200 transition group">
                        <div class="flex items-start justify-between">
                            <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            </div>
                            <span class="text-xs text-gray-400">{{ $project->created_at->diffForHumans() }}</span>
                        </div>
                        <h3 class="font-semibold text-gray-900 mt-3 group-hover:text-indigo-600 transition">{{ $project->name }}</h3>
                        @if ($project->description)
                            <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $project->description }}</p>
                        @endif
                        <div class="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between">
                            <span class="text-xs text-gray-400">{{ $project->generations_count ?? 0 }} generations</span>
                            <span class="text-xs text-indigo-500 font-medium opacity-0 group-hover:opacity-100 transition">View &rarr;</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                <p class="text-gray-500 text-sm">Belum ada project. Buat generasi pertama untuk memulai.</p>
            </div>
        @endif
    </div>

</div>
@endsection
