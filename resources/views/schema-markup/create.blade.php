@extends('layouts.app')

@section('title', 'Buat Schema Markup')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('schema-markup.index') }}" class="text-gray-400 hover:text-gray-600">&larr; Kembali</a>
        <h1 class="text-2xl font-bold">Buat Schema Markup</h1>
    </div>

    <form action="{{ route('schema-markup.store') }}" method="POST" id="schemaForm" class="space-y-6">
        @csrf

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-4">
            <h2 class="font-semibold text-lg">Informasi Dasar</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Label <span class="text-gray-400 font-normal">(opsional)</span></label>
                <input type="text" name="name" value="{{ old('name', $autoFill['_content_title'] ?? '') }}" placeholder="Contoh: Schema Artikel Utama"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Schema *</label>
                    <select name="schema_type" id="schemaType" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        onchange="changeType(this.value)">
                        <option value="">-- Pilih Tipe --</option>
                        @foreach ($types as $key => $label)
                        <option value="{{ $key }}" {{ old('schema_type', request('type', 'Article')) == $key ? 'selected' : '' }}>{{ $label }} ({{ $key }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL Target <span class="text-gray-400 font-normal">(opsional)</span></label>
                    <input type="url" name="target_url" id="targetUrl" value="{{ old('target_url', $autoFill['target_url'] ?? '') }}" placeholder="https://example.com/artikel"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Auto-fill dari Konten <span class="text-gray-400 font-normal">(opsional)</span></label>
                <select id="sourceContent" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    onchange="autoFillContent(this.value)">
                    <option value="">-- Pilih konten yang sudah selesai --</option>
                    @foreach ($contents as $c)
                    <option value="{{ $c->id }}" {{ ($selectedContent && $selectedContent->id == $c->id) ? 'selected' : '' }}>
                        {{ Str::limit($c->meta_title ?: $c->target_keyword, 60) }} ({{ $c->created_at->format('d/m/Y') }})
                    </option>
                    @endforeach
                </select>
            </div>

            <input type="hidden" name="sourceable_type" id="sourceableType" value="{{ $autoFill['sourceable_type'] ?? '' }}">
            <input type="hidden" name="sourceable_id" id="sourceableId" value="{{ $autoFill['sourceable_id'] ?? '' }}">
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-4">
            <h2 class="font-semibold text-lg">AI Enhancement</h2>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="use_ai" value="1" {{ old('use_ai') ? 'checked' : '' }}
                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                <div>
                    <span class="text-sm font-medium text-gray-700">Gunakan AI untuk generate schema</span>
                    <p class="text-xs text-gray-400">AI akan melengkapi data yang kurang dan mengoptimalkan struktur schema</p>
                </div>
            </label>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-4" id="fieldsContainer">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-lg">Data Schema</h2>
                <span id="typeLabel" class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">{{ old('schema_type', request('type', 'Article')) }}</span>
            </div>
            <div id="dynamicFields" class="space-y-3" data-current-type="{{ old('schema_type', request('type', 'Article')) }}">
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('schema-markup.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Batal</a>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Generate Schema
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const TYPES = @json(\App\Models\SchemaMarkup::TYPES);
const autoFill = @json($autoFill);
const selectedType = '{{ old('schema_type', request('type', 'Article')) }}';

function changeType(type) {
    document.getElementById('typeLabel').textContent = TYPES[type] || type;
    document.getElementById('dynamicFields').dataset.currentType = type;
    buildFields(type, autoFill);
}

function buildFields(type, data) {
    const container = document.getElementById('dynamicFields');
    container.innerHTML = '';

    if (!type) return;

    fetch(`{{ route('schema-markup.autofill') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({
            schema_type: type,
            content_id: document.getElementById('sourceContent').value || null,
        }),
    })
    .then(r => r.json())
    .then(fillData => {
        if (fillData._content_id) {
            data = fillData;
        }
        renderFields(type, data);
    })
    .catch(() => renderFields(type, data));
}

function renderFields(type, data) {
    const container = document.getElementById('dynamicFields');
    const defs = FIELD_DEFS[type];
    if (!defs) { container.innerHTML = '<p class="text-sm text-gray-400">Pilih tipe schema untuk melihat field.</p>'; return; }

    let html = '';
    defs.forEach(def => {
        const val = data[def.key] !== undefined ? data[def.key] : (def.default || '');
        const required = def.required ? 'required' : '';
        const placeholder = def.placeholder || `Masukkan ${def.label.toLowerCase()}`;

        if (def.type === 'hidden') {
            html += `<input type="hidden" name="data[${def.key}]" value="${escapeHtml(String(val))}">`;
        } else if (def.type === 'repeater') {
            html += renderRepeater(def, val);
        } else if (def.type === 'select') {
            html += renderSelectField(def, val, required);
        } else if (def.type === 'textarea') {
            html += `<div>
                <label class="block text-sm font-medium text-gray-700 mb-1">${def.label} ${required ? '<span class="text-red-500">*</span>' : ''}</label>
                <textarea name="data[${def.key}]" rows="3" placeholder="${placeholder}" ${required}
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">${escapeHtml(String(val))}</textarea>
            </div>`;
        } else if (def.type === 'date') {
            html += `<div>
                <label class="block text-sm font-medium text-gray-700 mb-1">${def.label}</label>
                <input type="date" name="data[${def.key}]" value="${val || ''}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>`;
        } else if (def.type === 'datetime-local') {
            html += `<div>
                <label class="block text-sm font-medium text-gray-700 mb-1">${def.label}</label>
                <input type="datetime-local" name="data[${def.key}]" value="${val || ''}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>`;
        } else {
            const step = def.step ? `step="${def.step}"` : '';
            const max = def.max ? `max="${def.max}"` : '';
            const inputType = def.type || 'text';
            html += `<div>
                <label class="block text-sm font-medium text-gray-700 mb-1">${def.label} ${required ? '<span class="text-red-500">*</span>' : ''}</label>
                <input type="${inputType}" name="data[${def.key}]" value="${escapeHtml(String(val))}"
                    placeholder="${placeholder}" ${step} ${max} ${required}
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>`;
        }
    });

    container.innerHTML = html;
}

function renderSelectField(def, val, required) {
    let opts = '<option value="">-- Pilih --</option>';
    for (const [k, v] of Object.entries(def.options)) {
        const sel = (String(val) === String(k)) ? 'selected' : '';
        opts += `<option value="${k}" ${sel}>${v}</option>`;
    }
    return `<div>
        <label class="block text-sm font-medium text-gray-700 mb-1">${def.label}</label>
        <select name="data[${def.key}]" ${required}
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">${opts}</select>
    </div>`;
}

function renderRepeater(def, val) {
    let items = Array.isArray(val) && val.length ? val : (def.defaults || [{}]);
    let html = `<div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">${def.label}</label>`;

    items.forEach((item, idx) => {
        html += `<div class="repeater-item bg-gray-50 p-3 rounded-lg border border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">`;
        (def.fields || []).forEach(f => {
            const fval = item[f.key] !== undefined ? item[f.key] : '';
            if (f.type === 'textarea') {
                html += `<div>
                    <label class="block text-xs text-gray-500">${f.label}</label>
                    <textarea name="data[${def.key}][${idx}][${f.key}]" rows="2"
                        class="w-full border border-gray-300 rounded px-2 py-1 text-sm">${escapeHtml(String(fval))}</textarea>
                </div>`;
            } else {
                html += `<div>
                    <label class="block text-xs text-gray-500">${f.label}</label>
                    <input type="${f.type || 'text'}" name="data[${def.key}][${idx}][${f.key}]" value="${escapeHtml(String(fval))}"
                        class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                </div>`;
            }
        });
        html += `</div>
            <button type="button" onclick="this.closest('.repeater-item').remove()"
                class="mt-2 text-xs text-red-500 hover:text-red-700">Hapus</button>
        </div>`;
    });

    const fieldsJson = escapeHtml(JSON.stringify(def.fields || []));
    html += `<button type="button" onclick="addRepeaterItem('${def.key}', '${fieldsJson}')"
        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Tambah Item</button>
    </div>`;
    return html;
}

function addRepeaterItem(key, fieldsJson) {
    const fields = JSON.parse(fieldsJson);
    const parent = document.querySelector(`[name^="data[${key}]"]`)?.closest('.space-y-2');
    if (!parent) return;

    const existing = parent.querySelectorAll(`[name^="data[${key}]["]`);
    const idx = existing.length;

    let html = `<div class="repeater-item bg-gray-50 p-3 rounded-lg border border-gray-200 mt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">`;
    fields.forEach(f => {
        if (f.type === 'textarea') {
            html += `<div>
                <label class="block text-xs text-gray-500">${f.label}</label>
                <textarea name="data[${key}][${idx}][${f.key}]" rows="2"
                    class="w-full border border-gray-300 rounded px-2 py-1 text-sm"></textarea>
            </div>`;
        } else {
            html += `<div>
                <label class="block text-xs text-gray-500">${f.label}</label>
                <input type="${f.type || 'text'}" name="data[${key}][${idx}][${f.key}]"
                    class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
            </div>`;
        }
    });
    html += `</div>
        <button type="button" onclick="this.closest('.repeater-item').remove()"
            class="mt-2 text-xs text-red-500 hover:text-red-700">Hapus</button>
    </div>`;

    parent.querySelector('button:last-child').insertAdjacentHTML('beforebegin', html);
}

function autoFillContent(contentId) {
    const type = document.getElementById('schemaType').value;
    if (!type) { alert('Pilih tipe schema terlebih dahulu'); return; }
    if (!contentId) { buildFields(type, {}); return; }

    fetch('{{ route("schema-markup.autofill") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ schema_type: type, content_id: contentId }),
    })
    .then(r => r.json())
    .then(data => {
        buildFields(type, data);
        const nameField = document.querySelector('input[name="name"]');
        if (!nameField.value && data._content_title) {
            nameField.value = data._content_title + ' (' + (TYPES[type] || type) + ')';
        }
        if (data._content_id) {
            document.getElementById('sourceableType').value = data._sourceable_type || '';
            document.getElementById('sourceableId').value = data._content_id;
        }
    })
    .catch(e => console.error(e));
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

const FIELD_DEFS = {
    Article: [
        { key: 'headline', label: 'Headline', type: 'text', required: true },
        { key: 'description', label: 'Description', type: 'textarea', required: true },
        { key: 'article_body', label: 'Article Body', type: 'textarea' },
        { key: 'keyword', label: 'Keywords', type: 'text' },
        { key: 'date_published', label: 'Date Published', type: 'date' },
        { key: 'date_modified', label: 'Date Modified', type: 'date' },
        { key: 'author_name', label: 'Author Name', type: 'text' },
        { key: 'author_type', label: 'Author Type', type: 'select', options: { Person: 'Person', Organization: 'Organization' }, default: 'Person' },
        { key: 'image_url', label: 'Image URL', type: 'url' },
        { key: 'publisher_name', label: 'Publisher Name', type: 'text' },
        { key: 'publisher_type', label: 'Publisher Type', type: 'select', options: { Organization: 'Organization', Person: 'Person' }, default: 'Organization' },
        { key: 'publisher_logo', label: 'Publisher Logo URL', type: 'url' },
    ],
    FAQPage: [
        { key: 'headline', label: 'Topik FAQ', type: 'text' },
        { key: 'faq_items', label: 'Pertanyaan & Jawaban', type: 'repeater',
            fields: [
                { key: 'question', label: 'Pertanyaan', type: 'text' },
                { key: 'answer', label: 'Jawaban', type: 'textarea' },
            ],
            defaults: [{ question: '', answer: '' }]
        },
    ],
    Product: [
        { key: 'name', label: 'Nama Produk', type: 'text', required: true },
        { key: 'description', label: 'Deskripsi', type: 'textarea', required: true },
        { key: 'brand', label: 'Brand', type: 'text' },
        { key: 'image_url', label: 'Image URL', type: 'url' },
        { key: 'sku', label: 'SKU', type: 'text' },
        { key: 'gtin', label: 'GTIN', type: 'text' },
        { key: 'price', label: 'Harga', type: 'number', step: '0.01' },
        { key: 'price_currency', label: 'Mata Uang', type: 'text', placeholder: 'IDR' },
        { key: 'availability', label: 'Availability', type: 'select', options: {
            'https://schema.org/InStock': 'In Stock',
            'https://schema.org/OutOfStock': 'Out of Stock',
            'https://schema.org/PreOrder': 'Pre Order',
        }, default: 'https://schema.org/InStock' },
        { key: 'review_rating', label: 'Rating (0-5)', type: 'number', step: '0.1', max: '5' },
        { key: 'review_count', label: 'Jumlah Review', type: 'number' },
    ],
    LocalBusiness: [
        { key: 'business_name', label: 'Nama Bisnis', type: 'text', required: true },
        { key: 'description', label: 'Deskripsi', type: 'textarea' },
        { key: 'address', label: 'Alamat', type: 'textarea' },
        { key: 'telephone', label: 'Telepon', type: 'text' },
        { key: 'email', label: 'Email', type: 'email' },
        { key: 'opening_hours', label: 'Jam Operasional', type: 'text', placeholder: 'Mo-Fr 09:00-17:00' },
        { key: 'image_url', label: 'Image URL', type: 'url' },
        { key: 'url', label: 'Website URL', type: 'url' },
        { key: 'same_as', label: 'Social Media (pisahkan koma)', type: 'text', placeholder: 'https://facebook.com/..., https://instagram.com/...' },
    ],
    BreadcrumbList: [
        { key: 'items', label: 'Breadcrumb Items', type: 'repeater',
            fields: [
                { key: 'name', label: 'Nama', type: 'text' },
                { key: 'url', label: 'URL', type: 'url' },
            ],
            defaults: [
                { name: 'Home', url: '{{ url("/") }}' },
                { name: 'Kategori', url: '' },
                { name: 'Judul Halaman', url: '' },
            ]
        },
    ],
    Review: [
        { key: 'name', label: 'Judul Review', type: 'text', required: true },
        { key: 'review_body', label: 'Isi Review', type: 'textarea' },
        { key: 'review_rating', label: 'Rating (1-5)', type: 'number', step: '0.1', max: '5', required: true },
        { key: 'best_rating', label: 'Best Rating', type: 'number', default: '5' },
        { key: 'author_name', label: 'Nama Penulis', type: 'text' },
        { key: 'item_reviewed_name', label: 'Nama Item', type: 'text' },
        { key: 'item_reviewed_type', label: 'Tipe Item', type: 'select', options: { Product: 'Product', LocalBusiness: 'LocalBusiness', Organization: 'Organization', Movie: 'Movie', Book: 'Book' }, default: 'Product' },
    ],
    Recipe: [
        { key: 'name', label: 'Nama Resep', type: 'text', required: true },
        { key: 'description', label: 'Deskripsi', type: 'textarea', required: true },
        { key: 'category', label: 'Kategori', type: 'text', placeholder: 'Makanan Pembuka' },
        { key: 'cuisine', label: 'Masakan', type: 'text', placeholder: 'Indonesia' },
        { key: 'prep_time', label: 'Prep Time (ISO 8601)', type: 'text', placeholder: 'PT30M' },
        { key: 'cook_time', label: 'Cook Time (ISO 8601)', type: 'text', placeholder: 'PT45M' },
        { key: 'total_time', label: 'Total Time (ISO 8601)', type: 'text', placeholder: 'PT1H15M' },
        { key: 'recipe_yield', label: 'Hasil', type: 'text', placeholder: '4 porsi' },
        { key: 'ingredients', label: 'Bahan (1 baris per bahan)', type: 'textarea' },
        { key: 'instructions', label: 'Langkah (1 baris per langkah)', type: 'textarea' },
        { key: 'image_url', label: 'Image URL', type: 'url' },
        { key: 'nutrition_calories', label: 'Kalori', type: 'text', placeholder: '250 kalori' },
    ],
    VideoObject: [
        { key: 'name', label: 'Judul Video', type: 'text', required: true },
        { key: 'description', label: 'Deskripsi', type: 'textarea', required: true },
        { key: 'thumbnail_url', label: 'Thumbnail URL', type: 'url' },
        { key: 'upload_date', label: 'Upload Date', type: 'date' },
        { key: 'duration', label: 'Duration (ISO 8601)', type: 'text', placeholder: 'PT10M30S' },
        { key: 'embed_url', label: 'Embed URL', type: 'url' },
        { key: 'content_url', label: 'Content URL', type: 'url' },
    ],
    HowTo: [
        { key: 'name', label: 'Judul Panduan', type: 'text', required: true },
        { key: 'description', label: 'Deskripsi', type: 'textarea', required: true },
        { key: 'total_time', label: 'Total Time (ISO 8601)', type: 'text', placeholder: 'PT1H' },
        { key: 'estimated_cost', label: 'Estimasi Biaya', type: 'text', placeholder: '50000' },
        { key: 'cost_currency', label: 'Mata Uang', type: 'text', placeholder: 'IDR' },
        { key: 'tools', label: 'Alat (pisahkan koma)', type: 'text' },
        { key: 'supplies', label: 'Bahan (pisahkan koma)', type: 'text' },
        { key: 'steps', label: 'Langkah (1 baris per langkah)', type: 'textarea' },
        { key: 'image_url', label: 'Image URL', type: 'url' },
    ],
    Event: [
        { key: 'name', label: 'Nama Event', type: 'text', required: true },
        { key: 'description', label: 'Deskripsi', type: 'textarea', required: true },
        { key: 'start_date', label: 'Start Date', type: 'datetime-local' },
        { key: 'end_date', label: 'End Date', type: 'datetime-local' },
        { key: 'location_name', label: 'Nama Lokasi', type: 'text' },
        { key: 'location_address', label: 'Alamat Lokasi', type: 'textarea' },
        { key: 'attendance_mode', label: 'Mode Kehadiran', type: 'select', options: {
            'https://schema.org/OfflineEventAttendanceMode': 'Offline',
            'https://schema.org/OnlineEventAttendanceMode': 'Online',
            'https://schema.org/MixedEventAttendanceMode': 'Campuran',
        }},
        { key: 'image_url', label: 'Image URL', type: 'url' },
        { key: 'performer', label: 'Performer', type: 'text' },
        { key: 'performer_type', label: 'Performer Type', type: 'select', options: { Person: 'Person', MusicGroup: 'MusicGroup', Organization: 'Organization' }},
        { key: 'organizer_name', label: 'Organizer', type: 'text' },
        { key: 'organizer_type', label: 'Organizer Type', type: 'select', options: { Organization: 'Organization', Person: 'Person' }},
        { key: 'organizer_url', label: 'Organizer URL', type: 'url' },
        { key: 'offers_price', label: 'Harga Tiket', type: 'number', step: '0.01' },
        { key: 'offers_label', label: 'Label Tiket', type: 'text', placeholder: 'Tiket Masuk' },
        { key: 'offers_currency', label: 'Mata Uang', type: 'text', placeholder: 'IDR' },
    ],
};

document.addEventListener('DOMContentLoaded', function() {
    const type = document.getElementById('schemaType').value;
    if (type) {
        document.getElementById('typeLabel').textContent = TYPES[type] || type;
        buildFields(type, autoFill);
    }
});
</script>
@endpush
