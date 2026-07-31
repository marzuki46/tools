@extends('layouts.app')

@section('title', 'Buat Cluster Baru')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Buat Cluster Baru</h4>

    <form action="{{ route('seocluster.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">Informasi Cluster</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Cluster</label>
                            <input type="text" name="name" class="form-control" required
                                placeholder="e.g. Jasa Web, Digital Marketing, dll">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Parent Keyword</label>
                            <input type="text" name="parent_keyword" class="form-control" required
                                placeholder="e.g. jasa pembuatan website">
                            <small class="text-muted">Keyword utama cluster ini</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="2"
                                placeholder="Deskripsi cluster (opsional)"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jadwal</label>
                            <select name="schedule" class="form-select">
                                <option value="manual">Manual</option>
                                <option value="daily">Daily</option>
                                <option value="every_6h">Every 6 Hours</option>
                                <option value="every_12h">Every 12 Hours</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">Pengaturan Gambar</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Keyword Gambar</label>
                            <input type="text" name="image_keyword" class="form-control"
                                placeholder="Kosongkan = pakai parent keyword">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sumber Gambar</label>
                            <select name="image_source" class="form-select">
                                <option value="duckduckgo">DuckDuckGo</option>
                                <option value="bing">Bing</option>
                                <option value="unsplash">Unsplash</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gambar / Artikel</label>
                                    <input type="number" name="image_per_article" class="form-control" value="3" min="0" max="10">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kualitas WebP</label>
                                    <input type="number" name="webp_quality" class="form-control" value="80" min="10" max="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Daftar Keyword</span>
                <small class="text-muted">1 keyword per baris</small>
            </div>
            <div class="card-body">
                <textarea name="keywords" class="form-control" rows="12" required
                    placeholder="website murah&#10;jasa buat website&#10;pembuatan website profesional&#10;harga website company profile&#10;..."></textarea>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Buat Cluster</button>
        <a href="{{ route('seocluster.index') }}" class="btn btn-outline-secondary">Batal</a>
    </form>
</div>
@endsection
