@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>GEO Content — 4 Fase</h4>
        <a href="{{ route('geocontent.create') }}" class="btn btn-primary">Buat Project Baru</a>
    </div>
    <table class="table table-bordered">
        <thead><tr><th>Keyword Utama</th><th>Mode</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        @forelse($projects as $p)
            <tr><td>{{ $p->keyword_utama }}</td><td>{{ $p->mode }}</td><td><span class="badge bg-info">{{ $p->status }}</span></td><td><a href="{{ route('geocontent.show', $p) }}" class="btn btn-sm btn-outline-primary">Detail</a></td></tr>
        @empty
            <tr><td colspan="4" class="text-center">Belum ada project GEO.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $projects->links() }}
</div>
@endsection
