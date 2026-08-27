@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h4>Project: {{ $project->keyword_utama }} <span class="badge bg-info">{{ $project->status }}</span> <span class="badge bg-secondary">{{ $project->mode }}</span></h4>

    @if($project->error_message)
        <div class="alert alert-danger">{{ $project->error_message }}</div>
    @endif

    <div class="card mb-3"><div class="card-header">Fase 1 — Riset Keyword (LSI & Entities)</div>
        <div class="card-body">
            @if($project->keywordResearch)
                <strong>Keyword Utama:</strong> {{ $project->keyword_utama }}<br>
                <strong>LSI:</strong> {{ collect($project->keywordResearch->lsi_keywords)->pluck('keyword')->implode(', ') }}<br>
                <strong>Entities:</strong> {{ collect($project->keywordResearch->entities)->pluck('name')->implode(', ') }}
            @else
                <em>Menunggu riset...</em>
            @endif
        </div>
    </div>

    <div class="card mb-3"><div class="card-header">Fase 2 — Fakta Kompetitor (brand scrubbed)</div>
        <div class="card-body">
            @forelse($project->sourceFacts as $fact)
                <div class="border p-2 mb-2">
                    <small>{{ $fact->source_url }} — <span class="badge bg-{{ $fact->fetch_status==='success'?'success':'danger' }}">{{ $fact->fetch_status }}</span></small>
                    @if($fact->is_synthesis)<span class="badge bg-primary">Sintesis</span>@endif
                    <div class="mt-1">{{ \Illuminate\Support\Str::limit($fact->sanitized_facts ?? $fact->fetch_error, 400) }}</div>
                </div>
            @empty
                <em>Belum ada fakta.</em>
            @endforelse
            <form method="POST" action="{{ route('geocontent.fetchFacts', $project) }}">@csrf<button class="btn btn-sm btn-outline-primary">Fetch Ulang Fakta</button></form>
        </div>
    </div>

    <div class="card mb-3"><div class="card-header">Fase 3 — Pertanyaan Kritis + Panel {keyword, LSI, Entitas}</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <ol>
                    @forelse($project->criticalFindings as $q)
                        <li>{{ $q->question }}</li>
                    @empty
                        <em>Belum ada pertanyaan kritis.</em>
                    @endforelse
                    </ol>
                </div>
                <div class="col-md-4">
                    <strong>Keyword Utama:</strong> {{ $project->keyword_utama }}<br>
                    <strong>LSI:</strong><ul>@foreach(($project->keywordResearch->lsi_keywords ?? []) as $lsi)<li>{{ $lsi['keyword'] ?? $lsi }}</li>@endforeach</ul>
                    <strong>Entitas:</strong><ul>@foreach(($project->keywordResearch->entities ?? []) as $e)<li>{{ $e['name'] ?? $e }}</li>@endforeach</ul>
                </div>
            </div>
            <form method="POST" action="{{ route('geocontent.generateQuestions', $project) }}">@csrf<button class="btn btn-sm btn-outline-primary">Generate Pertanyaan</button></form>
        </div>
    </div>

    <div class="card mb-3"><div class="card-header">Fase 4 — Konten AIDA + Before/After</div>
        <div class="card-body">
            @if($project->contents->isNotEmpty())
                @foreach($project->contents as $c)
                    <div class="border p-3 mb-3">
                        <strong>{{ $c->meta_title }}</strong> <small>{{ $c->word_count }} kata</small>
                        <div class="mt-2">{!! $c->final_content !!}</div>
                    </div>
                @endforeach
            @else
                <em>Belum ada konten.</em>
            @endif

            @if($project->diff)
                <h5>Perbandingan Before / After (mode revisi)</h5>
                <div class="border p-2 mb-2">{!! $project->diff->inline_diff_html !!}</div>
                <div class="border p-2">{!! $project->diff->side_by_side_html !!}</div>
            @endif

            <div class="mt-3 d-flex gap-2">
                <form method="POST" action="{{ route('geocontent.generate', $project) }}">@csrf<button class="btn btn-primary">Generate Konten AIDA</button></form>
                <form method="POST" action="{{ route('geocontent.review', $project) }}">@csrf<button class="btn btn-secondary">Cek Dulu (Review)</button></form>
                <form method="POST" action="{{ route('geocontent.publish', $project) }}">@csrf<button class="btn btn-success">Langsung Publish</button></form>
            </div>
        </div>
    </div>

    <a href="{{ route('geocontent.index') }}" class="btn btn-secondary">Kembali</a>
</div>
@endsection
