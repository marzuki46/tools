@extends('layouts.app')

@section('title', 'Buat Cluster Baru')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Buat Cluster Baru</h1>
        <p class="text-gray-500 text-sm mt-1">Kelompokkan keyword terkait dalam satu cluster</p>
    </div>

    <form action="{{ route('seocluster.store') }}" method="POST">
        @csrf

        <div class="grid grid-cols-2 gap-6 items-start">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-bold">Informasi Cluster</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Nama Cluster</label>
                        <input type="text" name="name" required placeholder="e.g. Jasa Web, Digital Marketing, dll"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Parent Keyword</label>
                        <input type="text" name="parent_keyword" required placeholder="e.g. jasa pembuatan website"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-xs text-gray-400 mt-1">Keyword utama cluster ini</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Deskripsi</label>
                        <textarea name="description" rows="2" placeholder="Deskripsi cluster (opsional)"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Jadwal</label>
                        <select name="schedule"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="manual">Manual</option>
                            <option value="daily">Daily</option>
                            <option value="every_6h">Every 6 Hours</option>
                            <option value="every_12h">Every 12 Hours</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-bold">Pengaturan Gambar</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Keyword Gambar</label>
                        <input type="text" name="image_keyword" placeholder="Kosongkan = pakai parent keyword"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Sumber Gambar</label>
                        <select name="image_source"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="duckduckgo">DuckDuckGo</option>
                            <option value="bing">Bing</option>
                            <option value="unsplash">Unsplash</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Gambar / Artikel</label>
                            <input type="number" name="image_per_article" value="3" min="0" max="10"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Kualitas WebP</label>
                            <input type="number" name="webp_quality" value="80" min="10" max="100"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-bold">Daftar Keyword</h3>
                <span class="text-xs text-gray-500">1 keyword per baris</span>
            </div>
            <div class="p-5">
                <textarea name="keywords" rows="12" required
                    placeholder="website murah&#10;jasa buat website&#10;pembuatan website profesional&#10;harga website company profile&#10;..."
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Buat Cluster
            </button>
            <a href="{{ route('seocluster.index') }}" class="px-5 py-2.5 rounded-lg text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-100 transition">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
