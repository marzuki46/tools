@extends('layouts.app')

@section('title', 'Riset Keyword Baru')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Riset Keyword Baru</h1>
        <p class="text-gray-500 text-sm mt-1">Masukkan keyword target untuk mendapatkan LSI keywords & entitas terkait</p>
    </div>

    <form action="{{ route('keywordresearch.store') }}" method="POST" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Target Keyword *</label>
            <input type="text" name="target_keyword" required placeholder="Contoh: kopi nusantara, sepatu running, dll"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Locale</label>
            <select name="locale" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="id">Indonesia</option>
                <option value="en">English</option>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah LSI Keywords</label>
                <input type="number" name="lsi_count" value="12" min="3" max="50"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-gray-400 mt-1">Min 3, maks 50</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Entities</label>
                <input type="number" name="entities_count" value="7" min="1" max="30"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-gray-400 mt-1">Min 1, maks 30</p>
            </div>
        </div>

        <hr class="border-gray-200">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Webhook URL <span class="text-gray-400 font-normal">(opsional)</span></label>
            <input type="url" name="webhook_url" placeholder="https://example.com/webhook"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <p class="text-xs text-gray-400 mt-1">Hasil akan dikirim ke URL ini via POST setelah selesai diproses.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret <span class="text-gray-400 font-normal">(opsional)</span></label>
            <input type="text" name="webhook_secret" placeholder="Untuk HMAC signature"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div class="pt-2">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Proses Riset
            </button>
        </div>
    </form>

    <div class="bg-gray-50 border border-gray-200 p-5 rounded-xl">
        <h3 class="text-sm font-semibold text-gray-700">Gunakan API</h3>
        <p class="text-xs text-gray-500 mt-1">Akses Keyword Research via API endpoint terpusat. <a href="{{ route('keywordresearch.index') }}" class="text-indigo-600 hover:text-indigo-800">Lihat dokumentasi API</a>.</p>
        <pre class="bg-white p-3 rounded-lg text-xs font-mono border border-gray-200 mt-2 overflow-x-auto">curl -X POST https://tools.juki.eu.org/api/v1/tool/keyword-research/research \
  -H "X-API-Key: juki_{your_api_key}" \
  -H "Content-Type: application/json" \
  -d '{"keyword":"...","lsi_count":12,"entities_count":7}'</pre>
    </div>
</div>
@endsection
