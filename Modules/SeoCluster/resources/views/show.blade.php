@extends('layouts.app')

@section('title', $cluster->name)

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('seocluster.index') }}" class="text-muted text-decoration-none">&larr; Kembali</a>
            <h4 class="mb-0 mt-1">{{ $cluster->name }}</h4>
        </div>
        <div>
            @if($cluster->status === 'draft' || $cluster->status === 'paused')
                <button class="btn btn-success" onclick="toggleCluster({{ $cluster->id }}, 'activate')">
                    <i class="fas fa-play"></i> Aktifkan
                </button>
            @elseif($cluster->status === 'active')
                <button class="btn btn-warning" onclick="toggleCluster({{ $cluster->id }}, 'pause')">
                    <i class="fas fa-pause"></i> Jeda
                </button>
            @endif
            <a href="{{ route('seocluster.edit', $cluster->id) }}" class="btn btn-outline-secondary">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body py-2 text-center">
                    <h5 class="text-success mb-0">{{ $progress['published'] }}</h5>
                    <small class="text-muted">Published</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body py-2 text-center">
                    <h5 class="text-warning mb-0">{{ $progress['pending'] }}</h5>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body py-2 text-center">
                    <h5 class="text-danger mb-0">{{ $progress['failed'] }}</h5>
                    <small class="text-muted">Failed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body py-2 text-center">
                    <h5 class="text-info mb-0">{{ $progress['total'] }}</h5>
                    <small class="text-muted">Total Keyword</small>
                </div>
            </div>
        </div>
    </div>

    <div class="progress mb-3" style="height: 8px;">
        <div class="progress-bar bg-success" style="width: {{ $progress['percent'] }}%"></div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span>Daftar Keyword</span>
                    <button class="btn btn-sm btn-primary" onclick="addKeyword({{ $cluster->id }})">
                        <i class="fas fa-plus"></i> Tambah
                    </button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Keyword</th>
                                <th>Status</th>
                                <th>URL</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($keywords as $kw)
                            <tr>
                                <td>{{ $kw->keyword }}</td>
                                <td>
                                    @php
                                        $badgeMap = [
                                            'pending' => 'secondary', 'researching' => 'info',
                                            'researched' => 'primary', 'generating' => 'info',
                                            'content_generated' => 'primary', 'publishing' => 'warning',
                                            'published' => 'success', 'failed' => 'danger',
                                        ];
                                        $badge = $badgeMap[$kw->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $badge }}">{{ str_replace('_', ' ', $kw->status) }}</span>
                                </td>
                                <td>
                                    @if($kw->post_url)
                                        <a href="{{ $kw->post_url }}" target="_blank" class="small">{{ Str::limit($kw->post_url, 30) }}</a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <form action="{{ route('seocluster.remove-keyword', [$cluster->id, $kw->id]) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Hapus keyword {{ $kw->keyword }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus">&times;</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">Belum ada keyword</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-2">{{ $keywords->links() }}</div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">Info Cluster</div>
                <div class="card-body small">
                    <div class="mb-1"><strong>Parent Keyword:</strong> {{ $cluster->parent_keyword }}</div>
                    <div class="mb-1"><strong>Status:</strong> {{ ucfirst($cluster->status) }}</div>
                    <div class="mb-1"><strong>Schedule:</strong> {{ ucfirst(str_replace('_', ' ', $cluster->schedule)) }}</div>
                    <div class="mb-1"><strong>Gambar:</strong> {{ $cluster->image_keyword }} ({{ $cluster->image_source }})</div>
                    <div class="mb-1"><strong>WebP Quality:</strong> {{ $cluster->webp_quality }}</div>
                    <div class="mb-1"><strong>Dibuat:</strong> {{ $cluster->created_at->format('d M Y H:i') }}</div>
                </div>
            </div>

            @if($logs->count())
            <div class="card mb-3">
                <div class="card-header">Log Terakhir</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        @foreach($logs as $log)
                        <tr>
                            <td><span class="badge bg-{{ $log->status === 'completed' ? 'success' : ($log->status === 'failed' ? 'danger' : 'secondary') }}">{{ $log->status }}</span></td>
                            <td>{{ $log->action }}</td>
                        </tr>
                        @endforeach
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
function toggleCluster(id, action) {
    fetch(`/keyword-clusters/${id}/${action}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
}

function addKeyword(clusterId) {
    const keyword = prompt('Masukkan keyword baru:');
    if (!keyword) return;
    fetch(`/keyword-clusters/${clusterId}/keywords`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ keyword })
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
}
</script>
@endsection
