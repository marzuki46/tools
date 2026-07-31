@extends('layouts.app')

@section('title', 'Edit ' . $cluster->name)

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Edit Cluster: {{ $cluster->name }}</h1>
        <p class="text-gray-500 text-sm mt-1">Perbarui pengaturan cluster</p>
    </div>

    <form action="{{ route('seocluster.update', $cluster->id) }}" method="POST">
        @csrf @method('PUT')

        <div class="grid grid-cols-2 gap-6 items-start">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-bold">Informasi Cluster</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Nama Cluster</label>
                        <input type="text" name="name" value="{{ $cluster->name }}" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Parent Keyword</label>
                        <input type="text" name="parent_keyword" value="{{ $cluster->parent_keyword }}" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Deskripsi</label>
                        <textarea name="description" rows="2"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ $cluster->description }}</textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Jadwal</label>
                        <select name="schedule"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach(['manual', 'daily', 'every_6h', 'every_12h'] as $s)
                            <option value="{{ $s }}" {{ $cluster->schedule === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="image_enabled" value="1" {{ $cluster->image_enabled ? 'checked' : '' }}
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Aktifkan gambar otomatis</span>
                    </label>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-bold">Pengaturan Gambar</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Keyword Gambar</label>
                        <input type="text" name="image_keyword" value="{{ $cluster->image_keyword }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Sumber Gambar</label>
                        <select name="image_source"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach(['duckduckgo', 'bing', 'unsplash'] as $src)
                            <option value="{{ $src }}" {{ $cluster->image_source === $src ? 'selected' : '' }}>{{ ucfirst($src) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Gambar / Artikel</label>
                            <input type="number" name="image_per_article" value="{{ $cluster->image_per_article }}" min="0" max="10"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Kualitas WebP</label>
                            <input type="number" name="webp_quality" value="{{ $cluster->webp_quality }}" min="10" max="100"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Simpan Perubahan
            </button>
            <a href="{{ route('seocluster.show', $cluster->id) }}" class="px-5 py-2.5 rounded-lg text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-100 transition">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
