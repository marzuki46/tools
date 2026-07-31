@extends('layouts.app')

@section('title', 'Content Analyzer')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Content Analyzer</h4>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Input Konten</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Target Keyword</label>
                        <input type="text" id="keyword" class="form-control" placeholder="e.g. jasa pembuatan website">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konten (HTML atau teks)</label>
                        <textarea id="content" class="form-control" rows="15" placeholder="Paste konten artikel di sini..."></textarea>
                    </div>
                    <button class="btn btn-primary" onclick="analyze()">Analisa Konten</button>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Hasil Analisa</div>
                <div class="card-body" id="resultArea">
                    <div class="text-center text-muted py-5">
                        Masukkan konten dan klik "Analisa Konten"
                    </div>
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

    document.getElementById('resultArea').innerHTML = '<div class="text-center py-5"><em>Menganalisa...</em></div>';

    try {
        const res = await fetch('{{ route('agentconnector.analyze') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ content, keyword })
        });
        const data = await res.json();
        renderResult(data);
    } catch (e) {
        document.getElementById('resultArea').innerHTML = '<div class="alert alert-danger">Gagal menganalisa konten.</div>';
    }
}

function renderResult(d) {
    const det = d.details || {};
    const issues = d.issues || [];

    function bar(score) {
        const color = score >= 80 ? 'success' : score >= 60 ? 'warning' : 'danger';
        return `<div class="progress" style="height:6px"><div class="progress-bar bg-${color}" style="width:${score}%"></div></div>`;
    }

    let html = `
        <div class="mb-3">
            <h5 class="mb-1">Skor Total: <strong>${d.total_score}</strong>/100</h5>
            ${bar(d.total_score)}
        </div>
        <table class="table table-sm small">
            <tr><td>SEO</td><td>${d.seo_score}/100</td><td>${bar(d.seo_score)}</td></tr>
            <tr><td>Struktur</td><td>${d.structure_score}/100</td><td>${bar(d.structure_score)}</td></tr>
            <tr><td>Readability</td><td>${d.readability_score}/100</td><td>${bar(d.readability_score)}</td></tr>
            <tr><td>Gambar</td><td>${d.image_score}/100</td><td>${bar(d.image_score)}</td></tr>
        </table>
        <hr>
        <h6>Detail</h6>
        <table class="table table-sm small">
    `;

    const rows = [
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

    rows.forEach(r => { html += `<tr><td>${r[0]}</td><td>${r[1]}</td></tr>`; });
    html += `</table>`;

    if (issues.length) {
        html += `<hr><h6 class="text-danger">Masalah Ditemukan</h6><ul class="small mb-0">`;
        issues.forEach(i => { html += `<li class="text-danger">${i}</li>`; });
        html += `</ul>`;
    }

    document.getElementById('resultArea').innerHTML = html;
}
</script>
@endsection
