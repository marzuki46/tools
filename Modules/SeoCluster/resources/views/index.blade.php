@extends('layouts.app')

@section('title', 'Keyword Clusters')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Keyword Clusters</h4>
        <a href="{{ route('seocluster.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Buat Cluster
        </a>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body py-2">
                    <h6 class="card-title mb-0">Total Cluster</h6>
                    <h3 class="mb-0">{{ $stats['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body py-2">
                    <h6 class="card-title mb-0">Aktif</h6>
                    <h3 class="mb-0">{{ $stats['active'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body py-2">
                    <h6 class="card-title mb-0">Published</h6>
                    <h3 class="mb-0">{{ $stats['total_published'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body py-2">
                    <h6 class="card-title mb-0">Antrian</h6>
                    <h3 class="mb-0">{{ $stats['queue_pending'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Parent Keyword</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Schedule</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clusters as $cluster)
                    <tr>
                        <td>
                            <a href="{{ route('seocluster.show', $cluster->id) }}">{{ $cluster->name }}</a>
                        </td>
                        <td>{{ $cluster->parent_keyword }}</td>
                        <td>
                            @php
                                $badge = ['draft' => 'secondary', 'active' => 'success', 'paused' => 'warning', 'completed' => 'info'][$cluster->status] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ ucfirst($cluster->status) }}</span>
                        </td>
                        <td>
                            @php $p = $cluster->progress(); @endphp
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: {{ $p['percent'] }}%"></div>
                                </div>
                                <small class="ms-2">{{ $p['published'] }}/{{ $p['total'] }}</small>
                            </div>
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $cluster->schedule)) }}</td>
                        <td>
                            <a href="{{ route('seocluster.edit', $cluster->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form action="{{ route('seocluster.destroy', $cluster->id) }}" method="POST" class="d-inline"
                                onsubmit="return confirm('Hapus cluster ini? Semua keyword akan ikut terhapus.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Belum ada cluster. <a href="{{ route('seocluster.create') }}">Buat cluster pertama</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $clusters->links() }}
    </div>
</div>
@endsection
