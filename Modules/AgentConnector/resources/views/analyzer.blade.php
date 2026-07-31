@extends('layouts.app')

@section('title', 'Content Analyzer')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Content Analyzer</h1>
        <p class="text-gray-500 text-sm mt-1">Analisa kualitas konten: SEO, struktur, readability, dan gambar</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-bold">Post dari Website WordPress</h3>
            <button onclick="loadWpPosts()"
                class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-medium text-gray-700 hover:bg-gray-100 transition">
                Muat Post
            </button>
        </div>
        <div class="p-5">
            <div id="wpPostList" class="space-y-2 max-h-72 overflow-y-auto">
                <p class="text-sm text-gray-400">Klik "Muat Post" untuk mengambil daftar artikel yang sudah dipublish.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="text-sm font-bold">Laporan Analisa Tersimpan</h3>
        </div>
        <div class="p-5">
            <div id="reportList" class="space-y-2">
                @forelse ($reports as $report)
                    <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-100 bg-gray-50">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $report->title }}</p>
                            <p class="text-xs text-gray-500">
                                Skor: <span class="font-semibold">{{ $report->total_score }}/100</span> ·
                                Status: <span class="font-semibold">{{ $report->status }}</span>
                                @if ($report->scheduled_at)
                                    · Jadwal: {{ $report->scheduled_at->format('d M Y H:i') }}
                                @endif
                            </p>
                            @if (!empty($report->issues))
                                <ul class="mt-1.5 space-y-0.5">
                                    @foreach (array_slice($report->issues, 0, 3) as $issue)
                                        <li class="text-xs text-red-600 flex items-start gap-1"><span>⚠️</span>{{ $issue }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        @if ($report->status === 'needs_optimization')
                            <button onclick="scheduleOptimization({{ $report->id }})"
                                class="flex-shrink-0 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700 transition">
                                Jadwalkan Optimasi
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Belum ada laporan analisa.</p>
                @endforelse
            </div>
        </div>
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
const WP_POSTS_URL = '{{ route('agentconnector.wp-posts') }}';
const ANALYZE_POST_URL = '{{ route('agentconnector.analyze-post') }}';
const SCHEDULE_URL = '{{ route('agentconnector.schedule-optimization') }}';
const CSRF = '{{ csrf_token() }}';

async function api(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body || {})
    });
    return res.json();
}

async function loadWpPosts() {
    const el = document.getElementById('wpPostList');
    el.innerHTML = '<p class="text-sm text-gray-400">Memuat post dari WordPress...</p>';

    const res = await fetch(WP_POSTS_URL, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();

    if (!data.success) {
        el.innerHTML = '<p class="text-sm text-red-600">' + (data.message || 'Gagal memuat post.') + '</p>';
        return;
    }

    const posts = data.data || [];
    if (!posts.length) {
        el.innerHTML = '<p class="text-sm text-gray-400">Tidak ada post yang dipublish.</p>';
        return;
    }

    el.innerHTML = posts.map(p => `
        <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">${p.title}</p>
                <a href="${p.url}" target="_blank" class="text-xs text-indigo-600 hover:underline truncate block">${p.url}</a>
            </div>
            <div class="flex-shrink-0 flex items-center gap-2">
                <input type="text" id="kw-${p.id}" placeholder="keyword (opsional)"
                    class="w-36 px-2 py-1.5 rounded-lg border border-gray-300 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button onclick="analyzePost(${p.id})"
                    class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700 transition">
                    Analisa
                </button>
            </div>
        </div>
    `).join('');
}

async function analyzePost(postId) {
    const keyword = document.getElementById('kw-' + postId).value.trim();
    document.getElementById('resultArea').innerHTML = '<div class="text-center py-12"><em class="text-sm text-gray-500">Menganalisa post dari WordPress...</em></div>';

    const data = await api(ANALYZE_POST_URL, { wp_post_id: postId, keyword });

    if (!data.success) {
        document.getElementById('resultArea').innerHTML = '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">' + (data.message || 'Gagal menganalisa post.') + '</div>';
        return;
    }

    const d = data.data;
    let html = '<div class="mb-4 p-4 rounded-xl bg-indigo-50 border border-indigo-100">';
    html += '<p class="text-xs text-gray-500 font-medium">Post: <a class="text-indigo-600 hover:underline" target="_blank" href="' + (d.post && d.post.url ? d.post.url : '#') + '">' + (d.post && d.post.title ? d.post.title : '') + '</a></p>';
    html += '</div>';
    document.getElementById('resultArea').innerHTML = html + renderResultHtml(d);

    if (d.total_score < 70) {
        document.getElementById('resultArea').innerHTML += `
            <div class="mt-4 p-4 rounded-xl bg-amber-50 border border-amber-200">
                <p class="text-sm text-amber-800 font-medium mb-2">Optimasi diperlukan (skor ${d.total_score}/100)</p>
                <label class="block text-xs font-semibold text-amber-700 uppercase tracking-wider mb-1.5">Jadwalkan Waktu Optimasi</label>
                <input type="datetime-local" id="schedule-dt" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
                <button onclick="scheduleOptimization(${d.report_id})"
                    class="mt-2 w-full bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-700 transition">
                    Jadwalkan Optimasi
                </button>
            </div>`;
    }
}

async function scheduleOptimization(reportId) {
    const input = document.getElementById('schedule-dt');
    if (input && !input.value) { alert('Pilih waktu optimasi terlebih dahulu.'); return; }
    const scheduledAt = input ? input.value : prompt('Masukkan waktu jadwal (YYYY-MM-DDTHH:MM):');

    const data = await api(SCHEDULE_URL, { report_id: reportId, scheduled_at: scheduledAt });
    if (data.success) {
        alert(data.message);
        location.reload();
    } else {
        alert(data.message || 'Gagal menjadwalkan.');
    }
}

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
    document.getElementById('resultArea').innerHTML = renderResultHtml(d);
}

function renderResultHtml(d) {
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

    return html;
}
</script>
@endsection
