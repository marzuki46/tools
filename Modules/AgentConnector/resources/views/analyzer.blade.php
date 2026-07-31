@extends('layouts.app')

@section('title', 'Content Analyzer')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Content Analyzer</h1>
        <p class="text-gray-500 text-sm mt-1">Analisa kualitas konten: SEO, struktur, readability, dan gambar</p>
    </div>

    <div class="grid grid-cols-2 gap-6 items-start">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-bold">Input Konten</h3>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label for="keyword" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Target Keyword</label>
                    <input type="text" id="keyword" placeholder="e.g. jasa pembuatan website"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="content" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Konten (HTML atau teks)</label>
                    <textarea id="content" rows="16" placeholder="Paste konten artikel di sini..."
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                <button onclick="analyze()"
                    class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    Analisa Konten
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-bold">Hasil Analisa</h3>
            </div>
            <div id="resultArea" class="p-5">
                <div class="text-center py-12">
                    <p class="text-3xl mb-2">📊</p>
                    <p class="text-sm text-gray-500">Masukkan konten dan klik "Analisa Konten"</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function analyze() {
    const content = document.getElementById('content').value.trim();
    const keyword = document.getElementById('keyword').value.trim();
    if (!content) { alert('Masukkan konten terlebih dahulu.'); return; }

    document.getElementById('resultArea').innerHTML = '<div class="text-center py-12"><em class="text-sm text-gray-500">Menganalisa...</em></div>';

    try {
        const res = await fetch('{{ route('agentconnector.analyze') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ content, keyword })
        });
        const data = await res.json();
        renderResult(data);
    } catch (e) {
        document.getElementById('resultArea').innerHTML = '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">Gagal menganalisa konten.</div>';
    }
}

function renderResult(d) {
    const det = d.details || {};
    const issues = d.issues || [];

    function barColor(score) {
        return score >= 80 ? 'bg-green-500' : score >= 60 ? 'bg-yellow-500' : 'bg-red-500';
    }
    function bar(score) {
        return `<div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden"><div class="${barColor(score)} h-2 rounded-full" style="width:${score}%"></div></div>`;
    }
    function scorePill(score) {
        const cls = score >= 80 ? 'bg-green-100 text-green-700' : score >= 60 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700';
        return `<span class="px-2 py-0.5 text-xs font-bold rounded-full ${cls}">${score}/100</span>`;
    }

    const categories = [
        ['SEO', d.seo_score],
        ['Struktur', d.structure_score],
        ['Readability', d.readability_score],
        ['Gambar', d.image_score],
    ];

    const detailRows = [
        ['Keyword Density', det.keyword_density + '%'],
        ['Total Kata', det.total_words],
        ['Total Kalimat', det.total_sentences],
        ['Total Paragraf', det.total_paragraphs],
        ['Total Heading', det.total_headings],
        ['Rata kata/kalimat', det.avg_words_per_sentence],
        ['Rata kalimat/paragraf', det.avg_sentences_per_paragraph],
        ['Gambar', det.total_images + ' (' + det.images_with_alt + ' alt, ' + det.images_webp + ' webp)'],
        ['Internal Link', det.internal_links],
        ['External Link', det.external_links],
        ['Readability', det.readability_score],
        ['Kata Sulit', det.complex_word_ratio + '%'],
        ['Kalimat Pasif', det.passive_voice_percent + '%'],
    ];

    let html = `
        <div class="flex items-center justify-between mb-4 p-4 rounded-xl bg-indigo-50 border border-indigo-100">
            <div>
                <p class="text-xs text-gray-500 font-medium">Skor Total</p>
                <p class="text-3xl font-bold text-indigo-700">${d.total_score}<span class="text-lg text-indigo-400">/100</span></p>
            </div>
            ${scorePill(d.total_score)}
        </div>

        <div class="space-y-3 mb-5">
    `;
    categories.forEach(c => {
        html += `
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm text-gray-700 font-medium">${c[0]}</span>
                    ${scorePill(c[1])}
                </div>
                ${bar(c[1])}
            </div>
        `;
    });
    html += `</div>`;

    html += `
        <div class="pt-4 border-t border-gray-200">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Detail</h4>
            <div class="grid grid-cols-1 gap-x-6 gap-y-2">
    `;
    detailRows.forEach(r => {
        html += `
            <div class="flex items-center justify-between py-1.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">${r[0]}</span>
                <span class="text-sm font-medium text-gray-800">${r[1]}</span>
            </div>
        `;
    });
    html += `</div></div>`;

    if (issues.length) {
        html += `
            <div class="mt-5 pt-4 border-t border-gray-200">
                <h4 class="text-xs font-semibold text-red-600 uppercase tracking-wider mb-3">Masalah Ditemukan</h4>
                <ul class="space-y-1.5">
        `;
        issues.forEach(i => {
            html += `<li class="flex items-start gap-2 text-sm text-red-700"><span class="mt-0.5 flex-shrink-0">⚠️</span>${i}</li>`;
        });
        html += `</ul></div>`;
    }

    document.getElementById('resultArea').innerHTML = html;
}
</script>
@endsection
