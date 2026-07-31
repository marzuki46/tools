@extends('layouts.app')

@section('title', 'Edit ' . $cluster->name)

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Edit Cluster: {{ $cluster->name }}</h4>

    <form action="{{ route('seocluster.update', $cluster->id) }}" method="POST">
        @csrf @method('PUT')

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">Informasi Cluster</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Cluster</label>
                            <input type="text" name="name" class="form-control" value="{{ $cluster->name }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Parent Keyword</label>
                            <input type="text" name="parent_keyword" class="form-control" value="{{ $cluster->parent_keyword }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="2">{{ $cluster->description }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jadwal</label>
                            <select name="schedule" class="form-select">
                                @foreach(['manual', 'daily', 'every_6h', 'every_12h'] as $s)
                                <option value="{{ $s }}" {{ $cluster->schedule === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="image_enabled" class="form-check-input" value="1" id="imgEnable"
                                {{ $cluster->image_enabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="imgEnable">Aktifkan gambar otomatis</label>
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
                            <input type="text" name="image_keyword" class="form-control" value="{{ $cluster->image_keyword }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sumber Gambar</label>
                            <select name="image_source" class="form-select">
                                @foreach(['duckduckgo', 'bing', 'unsplash'] as $src)
                                <option value="{{ $src }}" {{ $cluster->image_source === $src ? 'selected' : '' }}>{{ ucfirst($src) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gambar / Artikel</label>
                                    <input type="number" name="image_per_article" class="form-control" value="{{ $cluster->image_per_article }}" min="0" max="10">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kualitas WebP</label>
                                    <input type="number" name="webp_quality" class="form-control" value="{{ $cluster->webp_quality }}" min="10" max="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="{{ route('seocluster.show', $cluster->id) }}" class="btn btn-outline-secondary">Batal</a>
    </form>
</div>
@endsection
