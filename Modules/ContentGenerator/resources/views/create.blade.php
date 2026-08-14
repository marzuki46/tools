@extends('layouts.app')

@section('title', 'Buat Konten Baru')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Buat Konten Baru</h1>
        <p class="text-gray-500 text-sm mt-1">Masukkan keyword target untuk membuat konten dalam 3 fase</p>
    </div>

    <form action="{{ route('contentgenerator.store') }}" method="POST" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Target Keyword *</label>
            <input type="text" name="target_keyword" id="target-keyword" required placeholder="Contoh: kopi nusantara, digital marketing, dll"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Locale</label>
                <select name="locale" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="auto" selected>Auto / Ikuti Blog</option>
                    <option value="id">Indonesia</option>
                    <option value="en">English</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tone / Nada</label>
                <select name="tone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="informative">Informatif</option>
                    <option value="formal">Formal / Profesional</option>
                    <option value="casual">Santai / Casual</option>
                    <option value="persuasive">Persuasif / Marketing</option>
                    <option value="storytelling">Bercerita / Naratif</option>
                    <option value="instructional">Instruksional / Tutorial</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <input type="checkbox" name="include_external_links" id="include-external-links" value="1" checked
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <label for="include-external-links" class="block text-sm font-medium text-gray-700">
                Sertakan link eksternal <span class="text-gray-400 font-normal">(maksimal 1, opsional)</span>
            </label>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Hasil Riset Keyword <span class="text-gray-400 font-normal">(opsional)</span></label>
            <select name="keyword_research_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" id="kr-select">
                <option value="">-- Tidak menggunakan riset keyword --</option>
                @foreach ($researches as $r)
                <option value="{{ $r->id }}" data-keyword="{{ $r->target_keyword }}" data-lsi='{{ json_encode($r->lsi_keywords ?? []) }}' data-entities='{{ json_encode($r->entities ?? []) }}'>
                    {{ $r->target_keyword }} ({{ $r->lsi_keywords ? count($r->lsi_keywords) . ' LSI' : '' }}{{ $r->entities ? ', ' . count($r->entities) . ' entities' : '' }})
                </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Pilih hasil riset — keyword akan terisi otomatis. Riset yang sudah punya konten selesai tidak ditampilkan.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Profil Bisnis <span class="text-gray-400 font-normal">(opsional)</span></label>
            <select name="business_profile_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">-- Tidak menggunakan profil bisnis --</option>
                @foreach ($profiles as $p)
                <option value="{{ $p->id }}" {{ $p->is_default ? 'selected' : '' }}>
                    {{ $p->name }}{{ $p->business_name ? ' — ' . $p->business_name : '' }}
                </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Pilih profil bisnis yang informasinya akan disisipkan secara natural ke dalam artikel.</p>
        </div>

        <div class="pt-2">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Buat Konten
            </button>
        </div>
    </form>

    <div class="bg-indigo-50 border border-indigo-200 p-5 rounded-xl">
        <h3 class="text-sm font-semibold text-indigo-800">Alur Proses</h3>
        <ol class="text-sm text-indigo-700 mt-2 list-decimal list-inside space-y-1">
            <li><strong>Fase 1:</strong> AI membuat artikel lengkap berdasarkan keyword, LSI keywords, dan entities</li>
            <li><strong>Fase 2:</strong> AI membuat daftar pertanyaan kritis dari artikel yang dihasilkan</li>
            <li><strong>Fase 3:</strong> AI mengintegrasikan jawaban pertanyaan ke dalam artikel final</li>
            <li><strong>Meta:</strong> AI generate Meta Title & Description high-CTR otomatis</li>
        </ol>
    </div>

@push('scripts')
<script>
document.getElementById('kr-select')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const keyword = opt?.dataset?.keyword;
    if (keyword) {
        document.getElementById('target-keyword').value = keyword;
    }
});
</script>
@endpush
</div>
@endsection
