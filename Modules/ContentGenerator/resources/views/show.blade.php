@extends('layouts.app')

@section('title', $generation->target_keyword)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('contentgenerator.index') }}" class="text-gray-400 hover:text-gray-600">&larr; Kembali</a>
            <h1 class="text-2xl font-bold">{{ $generation->target_keyword }}</h1>
            <span class="px-2 py-0.5 text-xs font-medium rounded-full
                {{ $generation->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                {{ $generation->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                {{ in_array($generation->status, ['phase_1','phase_2','phase_3']) ? 'bg-blue-100 text-blue-700' : '' }}
                {{ $generation->status === 'draft' ? 'bg-yellow-100 text-yellow-700' : '' }}">
                {{ $generation->status === 'completed' ? 'Selesai' : $generation->status }}
            </span>
        </div>
        <div class="flex gap-2">
            @if ($generation->status === 'failed')
            <form action="{{ route('contentgenerator.retry-phase', [$generation->id, 1]) }}" method="POST" class="inline">
                @csrf
                <button class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-yellow-700 transition">Ulang</button>
            </form>
            @endif
            <form action="{{ route('contentgenerator.destroy', $generation->id) }}" method="POST" onsubmit="return confirm('Hapus konten ini?')">
                @csrf
                @method('DELETE')
                <button class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition">Hapus</button>
            </form>
        </div>
    </div>

    {{-- Progress Bar --}}
    @php
        $steps = [
            ['key' => 'draft', 'label' => 'Antrian', 'num' => 1],
            ['key' => 'phase_1', 'label' => 'Artikel', 'num' => 2],
            ['key' => 'phase_2', 'label' => 'Pertanyaan', 'num' => 3],
            ['key' => 'phase_3', 'label' => 'Konten Final', 'num' => 4],
        ];
        $statusOrder = ['draft' => 0, 'phase_1' => 1, 'phase_2' => 2, 'phase_3' => 3, 'completed' => 4, 'failed' => -1];
        $currentIdx = $statusOrder[$generation->status] ?? 0;
        $isFailed = $generation->status === 'failed';
    @endphp

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" id="progress-section">
        <div class="flex items-center justify-between">
            @foreach ($steps as $i => $step)
            @php
                $stepIdx = $statusOrder[$step['key']];
                $isCompleted = $currentIdx > $stepIdx && !$isFailed;
                $isCurrent = $currentIdx === $stepIdx || ($step['key'] === 'draft' && $currentIdx === 0 && !$isFailed);
                $isFailedStep = $isFailed && $step['key'] === ($generation->current_phase >= 1 ? 'phase_1' : 'draft');
            @endphp
            <div class="flex items-center flex-1">
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-500
                        {{ $isCompleted ? 'bg-green-500 text-white' : '' }}
                        {{ $isCurrent && !$isFailed ? 'bg-indigo-600 text-white ring-4 ring-indigo-200 animate-pulse' : '' }}
                        {{ !$isCompleted && !$isCurrent || $isFailed ? 'bg-gray-200 text-gray-500' : '' }}
                        {{ $isFailed && $isCurrent ? 'bg-red-500 text-white' : '' }}">
                        @if ($isCompleted)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        @elseif ($isFailed && $step['key'] === 'phase_1')
                        <span>!</span>
                        @else
                        {{ $step['num'] }}
                        @endif
                    </div>
                    <span class="text-xs mt-1.5 font-medium
                        {{ $isCompleted ? 'text-green-600' : '' }}
                        {{ $isCurrent && !$isFailed ? 'text-indigo-600' : '' }}
                        {{ !$isCompleted && !$isCurrent ? 'text-gray-400' : '' }}
                        {{ $isFailed && $isCurrent ? 'text-red-600' : '' }}">
                        {{ $step['label'] }}
                    </span>
                    @if ($isCurrent && !$isFailed)
                    <span class="text-[10px] text-indigo-400 mt-0.5" id="status-text">{{ $generation->status === 'draft' ? 'menunggu...' : 'memproses...' }}</span>
                    @endif
                </div>
                @if (!$loop->last)
                <div class="flex-1 h-1 mx-3 rounded transition-all duration-500
                    {{ $currentIdx > $stepIdx && !$isFailed ? 'bg-green-400' : 'bg-gray-200' }}
                    {{ $isFailed ? 'bg-gray-200' : '' }}">
                </div>
                @endif
            </div>
            @endforeach
        </div>

        @if ($isFailed)
        <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 flex items-center justify-between">
            <span>⛔ Proses gagal pada fase {{ $generation->current_phase }}</span>
            <form action="{{ route('contentgenerator.retry-phase', [$generation->id, max($generation->current_phase, 1)]) }}" method="POST">
                @csrf
                <button class="bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-red-700 transition">Coba Lagi</button>
            </form>
        </div>
        @endif

        @if ($isFailed && $generation->raw_response && !empty($generation->raw_response['error']))
        <details class="mt-2">
            <summary class="text-xs text-red-400 cursor-pointer hover:text-red-600">Lihat error detail</summary>
            <pre class="text-xs text-red-600 mt-1 bg-red-50 p-2 rounded overflow-auto max-h-32">{{ $generation->raw_response['error'] }}</pre>
        </details>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-xs text-gray-500 uppercase font-semibold">Locale</div>
            <div class="font-semibold mt-1 capitalize">{{ $generation->locale }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-xs text-gray-500 uppercase font-semibold">Tone</div>
            <div class="font-semibold mt-1 capitalize">{{ $generation->tone }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="text-xs text-gray-500 uppercase font-semibold">Dibuat</div>
            <div class="font-semibold mt-1">{{ $generation->created_at->format('d M Y H:i') }}</div>
        </div>
    </div>

    @if (!in_array($generation->status, ['completed', 'failed']))
    <div class="bg-indigo-50 border border-indigo-200 p-6 rounded-xl text-center">
        <p class="text-indigo-700 font-medium flex items-center justify-center gap-2">
            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            Memproses konten di fase {{ $generation->current_phase }}...
        </p>
    </div>
    @endif

    <div id="cg-tabs">
        <div class="border-b border-gray-200 flex gap-0 overflow-x-auto">
            <button onclick="switchTab('phase1')" id="tab-btn-phase1" class="px-5 py-3 text-sm font-medium border-b-2 transition border-indigo-600 text-indigo-600 whitespace-nowrap">Fase 1: Artikel</button>
            <button onclick="switchTab('phase2')" id="tab-btn-phase2" class="px-5 py-3 text-sm font-medium border-b-2 transition border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap">Fase 2: Pertanyaan</button>
            <button onclick="switchTab('phase3')" id="tab-btn-phase3" class="px-5 py-3 text-sm font-medium border-b-2 transition border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap">Fase 3: Konten Final</button>
            <button onclick="switchTab('meta')" id="tab-btn-meta" class="px-5 py-3 text-sm font-medium border-b-2 transition border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap">Meta SEO</button>
            <button onclick="switchTab('schema')" id="tab-btn-schema" class="px-5 py-3 text-sm font-medium border-b-2 transition border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap">Schema</button>
            <button onclick="switchTab('info')" id="tab-btn-info" class="px-5 py-3 text-sm font-medium border-b-2 transition border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap">Info</button>
        </div>

        {{-- Tab: Phase 1 --}}
        <div id="tab-content-phase1" class="pt-4">
            @if ($generation->phase_1_content)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 prose prose-sm max-w-none">
                {!! $generation->phase_1_content !!}
            </div>
            <div class="mt-3 flex items-center justify-between">
                @if ($generation->status === 'completed')
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500">Kualitas Fase 1:</span>
                    <div class="flex gap-1 rating-stars" data-target="phase1">
                        @for ($i = 1; $i <= 5; $i++)
                        <button onclick="setRating({{ $i }}, 'phase1')" class="text-xl {{ $memory && $memory->quality_score >= $i ? 'text-yellow-400' : 'text-gray-300' }} hover:text-yellow-400 transition">★</button>
                        @endfor
                    </div>
                </div>
                @endif
                <form action="{{ route('contentgenerator.retry-phase', [$generation->id, 1]) }}" method="POST">
                    @csrf
                    <button class="text-sm text-yellow-600 hover:text-yellow-800">Ulang Fase 1</button>
                </form>
            </div>
            @else
            <div class="bg-gray-50 p-6 rounded-xl text-center text-gray-500">
                <p>Fase 1 belum selesai diproses.</p>
            </div>
            @endif
        </div>

        {{-- Tab: Phase 2 --}}
        <div id="tab-content-phase2" class="pt-4" style="display:none">
            @if ($generation->phase_2_questions)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-bold mb-4">Pertanyaan Kritis ({{ count($generation->phase_2_questions) }})</h2>
                <div class="space-y-3">
                    @foreach ($generation->phase_2_questions as $i => $q)
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <span class="bg-indigo-100 text-indigo-700 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">{{ $i + 1 }}</span>
                            <div>
                                <p class="font-medium text-gray-900">{{ $q['question'] ?? $q }}</p>
                                @if (!empty($q['answer']))
                                <p class="text-sm text-gray-600 mt-2">{{ $q['answer'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="mt-3 flex justify-end">
                <form action="{{ route('contentgenerator.retry-phase', [$generation->id, 2]) }}" method="POST">
                    @csrf
                    <button class="text-sm text-yellow-600 hover:text-yellow-800">Ulang Fase 2</button>
                </form>
            </div>
            @else
            <div class="bg-gray-50 p-6 rounded-xl text-center text-gray-500">
                <p>Fase 2 belum selesai diproses.</p>
            </div>
            @endif
        </div>

        {{-- Tab: Phase 3 --}}
        <div id="tab-content-phase3" class="pt-4" style="display:none">
            @if ($generation->phase_3_content)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 prose prose-sm max-w-none">
                {!! $generation->phase_3_content !!}
            </div>
            <div class="mt-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500">Kualitas:</span>
                    <div class="flex gap-1 rating-stars" data-target="phase3">
                        @for ($i = 1; $i <= 5; $i++)
                        <button onclick="setRating({{ $i }}, 'phase3')" class="text-xl {{ $memory && $memory->quality_score >= $i ? 'text-yellow-400' : 'text-gray-300' }} hover:text-yellow-400 transition">★</button>
                        @endfor
                    </div>
                </div>
                <form action="{{ route('contentgenerator.retry-phase', [$generation->id, 3]) }}" method="POST">
                    @csrf
                    <button class="text-sm text-yellow-600 hover:text-yellow-800">Ulang Fase 3</button>
                </form>
            </div>
            @if ($generation->status === 'completed')
            <div class="mt-4 bg-gray-50 border border-gray-200 p-5 rounded-xl">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">Jadikan Bahan Belajar AI</h3>
                <p class="text-xs text-gray-500 mb-3">Beri nilai artikel ini agar AI bisa belajar dari konten terbaik untuk generasi berikutnya.</p>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="is-reference" {{ $memory && $memory->is_reference ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Jadikan referensi utama</span>
                    </label>

                    <button onclick="saveFeedback()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                        Simpan Penilaian
                    </button>
                </div>

                <div class="mt-3">
                    <textarea id="feedback-text" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Catatan untuk AI (opsional): bagian mana yang bagus? gaya bahasa seperti apa?">{{ $memory->feedback ?? '' }}</textarea>
                </div>

                <div id="feedback-success" class="hidden mt-3 text-sm text-green-600 font-medium">✓ Penilaian disimpan sebagai bahan belajar AI.</div>
            </div>
            @endif
            @else
            <div class="bg-gray-50 p-6 rounded-xl text-center text-gray-500">
                <p>Fase 3 belum selesai diproses.</p>
            </div>
            @endif
        </div>

        {{-- Tab: Meta SEO --}}
        <div id="tab-content-meta" class="pt-4" style="display:none">
            @if ($generation->meta_title || $generation->meta_description)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">Meta Title & Description</h2>
                    <form action="{{ route('contentgenerator.generate-meta', $generation->id) }}" method="POST">
                        @csrf
                        <button class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">🔄 Regenerate</button>
                    </form>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-xs text-green-700 font-medium mb-1">PREVIEW (Google SERP)</div>
                    <div class="bg-white border border-gray-200 rounded p-3">
                        <p class="text-sm text-green-700 font-medium truncate">{{ $generation->meta_title }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $generation->target_keyword }} — {{ config('app.url') }}</p>
                        <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ $generation->meta_description }}</p>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-500 uppercase font-semibold tracking-wider">Meta Title</label>
                    <div class="flex items-center gap-2 mt-1">
                        <input type="text" readonly value="{{ $generation->meta_title }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 font-medium text-gray-800" id="meta-title-input">
                        <button onclick="copyMeta('meta-title-input')" class="text-xs text-indigo-600 hover:text-indigo-800 px-2 py-1 shrink-0">📋 Salin</button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($generation->meta_title) }} / 65 karakter</p>
                </div>

                <div>
                    <label class="text-xs text-gray-500 uppercase font-semibold tracking-wider">Meta Description</label>
                    <div class="flex items-center gap-2 mt-1">
                        <textarea readonly rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-800" id="meta-desc-input">{{ $generation->meta_description }}</textarea>
                        <button onclick="copyMeta('meta-desc-input')" class="text-xs text-indigo-600 hover:text-indigo-800 px-2 py-1 shrink-0 self-start mt-1">📋 Salin</button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($generation->meta_description) }} / 165 karakter</p>
                </div>
            </div>
            @elseif ($generation->status === 'completed')
            <div class="bg-gray-50 p-6 rounded-xl text-center text-gray-500">
                <p class="mb-2">Meta belum digenerate.</p>
                <form action="{{ route('contentgenerator.generate-meta', $generation->id) }}" method="POST">
                    @csrf
                    <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Generate Meta SEO</button>
                </form>
            </div>
            @else
            <div class="bg-gray-50 p-6 rounded-xl text-center text-gray-500">
                <p>Tunggu konten selesai diproses.</p>
            </div>
            @endif
        </div>

        {{-- Tab: Schema --}}
        <div id="tab-content-schema" class="pt-4" style="display:none">
            @if ($schema ?? false)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold">Schema Markup (Article)</h2>
                        <p class="text-xs text-gray-400">Auto-generated — <span class="text-purple-600">🤖 AI Enhanced</span></p>
                    </div>
                    <a href="{{ route('schema-markup.show', $schema->id) }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                        Lihat Detail &rarr;
                    </a>
                </div>

                <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs leading-relaxed max-h-64 overflow-y-auto">{{ json_encode($schema->generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>

                <div class="flex gap-3">
                    <button onclick="copySchema()" class="text-sm text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-3 py-1.5 rounded-lg font-medium">
                        📋 Copy JSON-LD
                    </button>
                    <a href="{{ route('schema-markup.show', $schema->id) }}" class="text-sm text-gray-600 hover:text-gray-800 bg-gray-50 px-3 py-1.5 rounded-lg font-medium">
                        ↻ Regenerate
                    </a>
                </div>
            </div>
            @elseif ($generation->status === 'completed')
            <div class="bg-gray-50 p-6 rounded-xl text-center text-gray-500">
                <p class="mb-2">Schema markup belum tersedia.</p>
                <p class="text-xs">Schema Article akan otomatis dibuat saat konten selesai diproses.</p>
            </div>
            @else
            <div class="bg-gray-50 p-6 rounded-xl text-center text-gray-500">
                <p>Tunggu konten selesai diproses.</p>
            </div>
            @endif
        </div>

        {{-- Tab: Info --}}
        <div id="tab-content-info" class="pt-4" style="display:none">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-4">
                <div>
                    <h3 class="font-semibold text-sm text-gray-700">Keyword Target</h3>
                    <p class="text-gray-900 mt-1">{{ $generation->target_keyword }}</p>
                </div>
                <div>
                    <h3 class="font-semibold text-sm text-gray-700">Hasil Riset Keyword Terkait</h3>
                    @if ($generation->keyword_research_id)
                    <p class="text-sm text-gray-500 mt-1">Menggunakan riset keyword #{{ $generation->keyword_research_id }}</p>
                    @else
                    <p class="text-sm text-gray-400 mt-1 italic">Tidak menggunakan riset keyword</p>
                    @endif
                </div>
                @if ($generation->lsi_keywords)
                <div>
                    <h3 class="font-semibold text-sm text-gray-700">LSI Keywords ({{ count($generation->lsi_keywords) }})</h3>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($generation->lsi_keywords as $lsi)
                        <span class="bg-gray-100 px-2 py-1 rounded text-xs text-gray-700">{{ $lsi['keyword'] ?? $lsi }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
                @if ($generation->entities)
                <div>
                    <h3 class="font-semibold text-sm text-gray-700">Entities ({{ count($generation->entities) }})</h3>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($generation->entities as $entity)
                        <span class="bg-gray-100 px-2 py-1 rounded text-xs text-gray-700">{{ $entity['name'] ?? $entity }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
                @if ($generation->status === 'failed' && $generation->raw_response)
                <div class="bg-red-50 border border-red-200 p-4 rounded-xl">
                    <h3 class="font-bold text-red-700 text-sm">Error Detail</h3>
                    <pre class="text-xs text-red-600 mt-1 overflow-auto">{{ json_encode($generation->raw_response, JSON_PRETTY_PRINT) }}</pre>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
function copyMeta(id) {
    const el = document.getElementById(id);
    navigator.clipboard?.writeText(el?.value || el?.textContent || '');
}

function switchTab(tab) {
    ['phase1', 'phase2', 'phase3', 'meta', 'schema', 'info'].forEach(t => {
        document.getElementById('tab-content-' + t).style.display = t === tab ? 'block' : 'none';
        const btn = document.getElementById('tab-btn-' + t);
        btn.className = 'px-5 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap ' +
            (t === tab ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700');
    });
}

function copySchema() {
    const json = document.querySelector('#tab-content-schema pre')?.textContent;
    if (json) {
        navigator.clipboard?.writeText(json);
        const btn = document.querySelector('#tab-content-schema .flex.gap-3 button:first-child');
        const orig = btn?.innerHTML || '';
        if (btn) { btn.innerHTML = '✅ Copied!'; setTimeout(() => btn.innerHTML = orig, 2000); }
    }
}

let currentRating = {{ $memory->quality_score ?? 0 }};

function setRating(val, phase) {
    currentRating = val;
    document.querySelectorAll('.rating-stars button').forEach((btn, i) => {
        btn.classList.toggle('text-yellow-400', i < val);
        btn.classList.toggle('text-gray-300', i >= val);
    });
}

function saveFeedback() {
    const isRef = document.getElementById('is-reference')?.checked || false;
    const feedback = document.getElementById('feedback-text')?.value || '';
    const rating = currentRating || 1;

    fetch('{{ route('contentgenerator.feedback', $generation->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ rating, is_reference: isRef, feedback })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('feedback-success').classList.remove('hidden');
            setTimeout(() => document.getElementById('feedback-success').classList.add('hidden'), 4000);
        }
    })
    .catch(() => alert('Gagal menyimpan penilaian'));
}

@if (!in_array($generation->status, ['completed', 'failed']))
(function poll() {
    fetch('{{ route('contentgenerator.status', $generation->id) }}')
        .then(r => r.json())
        .then(data => {
            if (data.is_done) {
                location.reload();
            } else {
                document.getElementById('status-text').textContent = 'fase ' + data.current_phase + '...';
                setTimeout(poll, 3000);
            }
        })
        .catch(() => setTimeout(poll, 5000));
})();
@endif
</script>
@endsection
