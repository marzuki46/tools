@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h4>Buat Project GEO Baru — 4 Fase</h4>
    <form method="POST" action="{{ route('geocontent.store') }}">
        @csrf
        <div class="mb-3">
            <label>Keyword Utama (Fase 1: riset LSI/entities)</label>
            <input type="text" name="keyword_utama" class="form-control" required placeholder="contoh: wisata di solo">
        </div>
        <div class="mb-3">
            <label>Mode</label>
            <select name="mode" class="form-select" required>
                <option value="baru">Konten Baru — Publish / Cek Dulu</option>
                <option value="revisi">Revisi Konten — Before/After via WP REST</option>
            </select>
        </div>
        <div class="mb-3" id="wp_post_id_group" style="display:none">
            <label>WP Post ID (untuk mode revisi)</label>
            <input type="number" name="wp_post_id" class="form-control" placeholder="123">
            <small class="text-muted">ID post WordPress yang akan direvisi (WP REST).</small>
        </div>
        <div class="mb-3">
            <label>URL Kompetitor (Fase 2: cek fakta, 3-5 URL, brand otomatis di-scrub)</label>
            <textarea name="competitor_urls[]" class="form-control" rows="5" placeholder="https://kompetitor1.com/artikel&#10;https://kompetitor2.com/artikel" required></textarea>
            <small class="text-muted">Masukkan 1 URL per baris. Sistem akan fetch & sanitasi tanpa brand kompetitor.</small>
            <div id="url-inputs">
                <input type="url" name="competitor_urls[]" class="form-control mt-2" placeholder="https://kompetitor2.com/...">
                <input type="url" name="competitor_urls[]" class="form-control mt-2" placeholder="https://kompetitor3.com/...">
            </div>
        </div>
        <button class="btn btn-primary">Buat & Jalankan Fase 1-2</button>
        <a href="{{ route('geocontent.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
<script>
document.querySelector('select[name="mode"]').addEventListener('change', e => {
    document.getElementById('wp_post_id_group').style.display = e.target.value === 'revisi' ? 'block' : 'none';
});
</script>
@endsection
