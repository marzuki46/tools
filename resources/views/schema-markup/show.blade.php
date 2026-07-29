@extends('layouts.app')

@section('title', 'Schema Markup: ' . $markup->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('schema-markup.index') }}" class="text-gray-400 hover:text-gray-600">&larr; Kembali</a>
            <h1 class="text-xl font-bold truncate max-w-md">{{ $markup->name }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('schema-markup.regenerate', $markup->id) }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="use_ai" value="{{ $markup->use_ai ? '0' : '1' }}">
                <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-3 py-1.5 rounded-lg">
                    {{ $markup->use_ai ? '↻ Regenerate (Manual)' : '🤖 Regenerate (AI)' }}
                </button>
            </form>
            <form action="{{ route('schema-markup.destroy', $markup->id) }}" method="POST" onsubmit="return confirm('Hapus schema markup ini?')">
                @csrf @method('DELETE')
                <button class="text-red-500 hover:text-red-700 text-sm">Hapus</button>
            </form>
        </div>
    </div>

    {{-- Info --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase font-semibold">Tipe Schema</p>
            <p class="text-sm font-medium mt-1">{{ $markup->getTypeLabel() }}
                <span class="text-gray-400 text-xs">({{ $markup->schema_type }})</span>
            </p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase font-semibold">Metode</p>
            <p class="text-sm mt-1">{{ $markup->use_ai ? '🤖 AI Generated' : 'Manual' }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase font-semibold">URL Target</p>
            <p class="text-sm mt-1 truncate">{{ $markup->target_url ?: '-' }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-400 uppercase font-semibold">Dibuat</p>
            <p class="text-sm mt-1">{{ $markup->created_at->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    {{-- Script Tag (Copy Ready) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold">Script Tag <span class="text-gray-400 font-normal text-xs">— siap copy-paste ke &lt;head&gt;</span></h2>
            <button onclick="copyScript()" class="text-sm text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-3 py-1.5 rounded-lg font-medium">
                📋 Copy Script Tag
            </button>
        </div>
        <div class="p-6">
            <pre id="scriptTag" class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs leading-relaxed whitespace-pre-wrap max-h-64 overflow-y-auto">{{ $scriptTag }}</pre>
        </div>
    </div>

    {{-- JSON Preview --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold">JSON-LD Preview</h2>
            <button onclick="copyJson()" class="text-sm text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-3 py-1.5 rounded-lg font-medium">
                📋 Copy JSON
            </button>
        </div>
        <div class="p-6">
            <pre id="jsonPreview" class="bg-gray-50 p-4 rounded-lg overflow-x-auto text-xs leading-relaxed whitespace-pre-wrap max-h-96 overflow-y-auto border border-gray-200">{{ $jsonPretty }}</pre>
        </div>
    </div>

    {{-- Validation --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="font-semibold mb-3">Validasi & Tools</h2>
        <div class="flex flex-wrap gap-3">
            <a href="https://search.google.com/test/rich-results?url={{ urlencode($markup->target_url ?: '') }}" target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 rounded-lg text-sm hover:bg-blue-100 transition">
                🧪 Google Rich Results Test
            </a>
            <a href="https://validator.schema.org/#url={{ urlencode($markup->target_url ?: '') }}" target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 bg-green-50 text-green-700 rounded-lg text-sm hover:bg-green-100 transition">
                ✅ Schema.org Validator
            </a>
            <a href="https://developers.google.com/search/docs/appearance/structured-data/search-gallery" target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 text-gray-700 rounded-lg text-sm hover:bg-gray-100 transition">
                📚 Search Gallery
            </a>
        </div>
        <p class="text-xs text-gray-400 mt-3">Tempel URL target di atas untuk validasi langsung, atau copy script tag untuk diimplementasikan.</p>
    </div>

    {{-- Raw Data --}}
    <details class="bg-white rounded-xl shadow-sm border border-gray-200">
        <summary class="px-6 py-4 cursor-pointer text-sm font-medium text-gray-600 hover:text-gray-800">Lihat Data Mentah</summary>
        <div class="px-6 pb-4">
            <pre class="bg-gray-50 p-4 rounded-lg text-xs overflow-x-auto max-h-64">@json($markup->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)</pre>
        </div>
    </details>
</div>
@endsection

@push('scripts')
<script>
function copyScript() {
    const el = document.getElementById('scriptTag');
    const text = el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = el.closest('.overflow-hidden').querySelector('button');
        const orig = btn.innerHTML;
        btn.innerHTML = '✅ Copied!';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}

function copyJson() {
    const el = document.getElementById('jsonPreview');
    const text = el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = el.closest('.overflow-hidden').querySelector('button');
        const orig = btn.innerHTML;
        btn.innerHTML = '✅ Copied!';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}
</script>
@endpush
