@extends('layouts.app')

@section('title', 'New Generation')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('metaadsimagegenerator.index') }}" class="text-indigo-600 text-sm hover:underline">&larr; Back to Meta Ads</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-8 py-5 border-b border-gray-100">
            <h1 class="text-2xl font-bold">New Ad Creative</h1>
            <p class="text-gray-500 text-sm mt-1">Generate AI-powered ad images for Facebook and Instagram</p>
        </div>

        <form method="POST" action="{{ route('metaadsimagegenerator.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="p-8 space-y-8">
                {{-- Section: Configuration --}}
                <div>
                    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Configuration</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                            <select name="project_id" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select project...</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Preset (optional)</label>
                            <select name="preset_id"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">No preset</option>
                                @foreach ($presets as $preset)
                                    <option value="{{ $preset->id }}">{{ $preset->name }} @if(is_null($preset->user_id)) (Global) @endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Brand Kit (optional)</label>
                            <select name="brand_kit_id"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">No brand kit</option>
                                @foreach ($brandKits as $kit)
                                    <option value="{{ $kit->id }}" @if($kit->is_default) selected @endif>{{ $kit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Divider --}}
                <hr class="border-gray-100">

                {{-- Section: AI Model --}}
                <div>
                    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">AI Image Generator</h2>
                    <div class="inline-flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-xl">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-green-800">Pollinations.ai (Free)</p>
                            <p class="text-xs text-green-600">Tidak perlu API key, langsung generate</p>
                        </div>
                    </div>
                    <input type="hidden" name="ai_provider" value="pollinations">
                </div>

                {{-- Divider --}}
                <hr class="border-gray-100">

                {{-- Section: Ad Copy --}}
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Ad Copy</h2>
                        <button type="button" id="generate-copy-btn"
                            class="px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-lg text-sm font-medium hover:from-purple-600 hover:to-pink-600 transition shadow-sm">
                            ✨ Generate 4 Variations
                        </button>
                    </div>

                    {{-- Product Name --}}
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Name *</label>
                        <input type="text" name="product_name" id="product_name" value="{{ old('product_name') }}" required placeholder="e.g. Kopi Nusantara Arabica"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    {{-- Copy Variations Cards --}}
                    <div id="copy-variations" class="hidden mb-5">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-medium text-gray-700">Pilih salah satu variasi:</p>
                            <button type="button" id="regenerate-copy-btn" class="text-xs text-purple-600 hover:underline">Regenerate</button>
                        </div>
                        <div id="copy-cards" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            {{-- Cards will be inserted here by JS --}}
                        </div>
                    </div>

                    {{-- Manual Input --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Headline</label>
                            <input type="text" name="headline" id="headline" value="{{ old('headline') }}" placeholder="e.g. Discover the Real Taste"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sub-headline</label>
                            <input type="text" name="sub_headline" id="sub_headline" value="{{ old('sub_headline') }}" placeholder="e.g. 100% Organic, Single Origin"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CTA</label>
                            <select name="cta" id="cta"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">No CTA</option>
                                <option value="Beli Sekarang">Beli Sekarang</option>
                                <option value="Daftar Gratis">Daftar Gratis</option>
                                <option value="Hubungi Kami">Hubungi Kami</option>
                                <option value="Pelajari Lebih">Pelajari Lebih</option>
                                <option value="Shop Now">Shop Now</option>
                                <option value="Sign Up Free">Sign Up Free</option>
                                <option value="Learn More">Learn More</option>
                                <option value="Contact Us">Contact Us</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vibe / Mood</label>
                            <select name="vibe"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select vibe...</option>
                                <option value="minimalis">Minimalis</option>
                                <option value="bold-promo">Bold Promo</option>
                                <option value="elegant">Elegant</option>
                                <option value="playful">Playful</option>
                                <option value="professional">Professional</option>
                                <option value="luxury">Luxury</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Target Audience</label>
                            <input type="text" name="target_audience" value="{{ old('target_audience') }}" placeholder="e.g. Millennials, age 25-40"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                {{-- Divider --}}
                <hr class="border-gray-100">

                {{-- Section: Images --}}
                <div>
                    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Reference Images</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-5 hover:border-indigo-300 transition">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Model Image <span class="text-gray-400 font-normal">(optional)</span></label>
                            <div class="flex flex-col items-center gap-3">
                                <div id="model-preview" class="w-full aspect-[4/3] bg-gray-50 border border-gray-200 rounded-lg flex items-center justify-center text-gray-400 text-xs overflow-hidden">
                                    <span class="preview-placeholder text-center px-4">Click "Choose File" to upload model image</span>
                                </div>
                                <label class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Choose File
                                    <input type="file" name="model_image" accept="image/png,image/jpeg,image/webp" class="hidden"
                                        onchange="previewImage(this, 'model-preview')">
                                </label>
                                <p class="text-xs text-gray-400">PNG, JPG, WebP. Max 5MB. Akan di-resize ke 1024px.</p>
                            </div>
                        </div>
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-5 hover:border-indigo-300 transition">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Product Image <span class="text-gray-400 font-normal">(optional)</span></label>
                            <div class="flex flex-col items-center gap-3">
                                <div id="product-preview" class="w-full aspect-[4/3] bg-gray-50 border border-gray-200 rounded-lg flex items-center justify-center text-gray-400 text-xs overflow-hidden">
                                    <span class="preview-placeholder text-center px-4">Click "Choose File" to upload product image</span>
                                </div>
                                <label class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Choose File
                                    <input type="file" name="product_image" accept="image/png,image/jpeg,image/webp" class="hidden"
                                        onchange="previewImage(this, 'product-preview')">
                                </label>
                                <p class="text-xs text-gray-400">PNG, JPG, WebP. Max 5MB. Akan di-resize ke 1024px.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Divider --}}
                <hr class="border-gray-100">

                {{-- Section: Notes --}}
                <div>
                    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Additional Notes</h2>
                    <textarea name="notes" rows="3" placeholder="Any specific instructions for the AI — describe the scene, mood, colors, or composition you want..."
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                </div>

                {{-- Divider --}}
                <hr class="border-gray-100">

                {{-- Section: Output Sizes --}}
                <div>
                    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Output Sizes</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-xl cursor-pointer hover:border-indigo-300 transition has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                            <input type="checkbox" name="sizes[]" value="1:1" checked
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Square 1:1</span>
                                <p class="text-xs text-gray-400">1080×1080 — Instagram Feed</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-xl cursor-pointer hover:border-indigo-300 transition has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                            <input type="checkbox" name="sizes[]" value="9:16" checked
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Story 9:16</span>
                                <p class="text-xs text-gray-400">1080×1920 — Instagram/FB Stories</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-xl cursor-pointer hover:border-indigo-300 transition has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                            <input type="checkbox" name="sizes[]" value="1.91:1" checked
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Landscape 1.91:1</span>
                                <p class="text-xs text-gray-400">1200×628 — Facebook Feed</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="px-8 py-5 bg-gray-50 border-t border-gray-100 rounded-b-xl">
                <button type="submit"
                    class="w-full bg-indigo-600 text-white py-3.5 rounded-xl font-semibold hover:bg-indigo-700 transition shadow-sm text-lg">
                    Generate Ad Creative
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

let currentVariations = [];

function selectVariation(index) {
    const v = currentVariations[index];
    if (!v) return;

    document.getElementById('headline').value = v.headline || '';
    document.getElementById('sub_headline').value = v.sub_headline || '';

    if (v.cta) {
        const ctaSelect = document.getElementById('cta');
        const opt = Array.from(ctaSelect.options).find(o => o.value === v.cta);
        if (opt) ctaSelect.value = v.cta;
    }

    // Highlight selected card
    document.querySelectorAll('.copy-card').forEach((card, i) => {
        card.classList.toggle('ring-2', i === index);
        card.classList.toggle('ring-indigo-500', i === index);
        card.classList.toggle('bg-indigo-50', i === index);
    });
}

function renderVariationCards(variations) {
    currentVariations = variations;
    const container = document.getElementById('copy-cards');
    const labels = ['Professional', 'Casual', 'Urgency', 'Benefit'];
    const colors = ['border-blue-200', 'border-green-200', 'border-red-200', 'border-purple-200'];

    container.innerHTML = variations.map((v, i) => `
        <div class="copy-card border-2 ${colors[i]} rounded-xl p-4 cursor-pointer hover:shadow-md transition"
             onclick="selectVariation(${i})">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-gray-500 uppercase">${labels[i]}</span>
                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <p class="font-semibold text-gray-900 text-sm">${v.headline || '-'}</p>
            <p class="text-xs text-gray-500 mt-1">${v.sub_headline || '-'}</p>
            <span class="inline-block mt-2 px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">${v.cta || '-'}</span>
        </div>
    `).join('');

    document.getElementById('copy-variations').classList.remove('hidden');
}

async function fetchVariations() {
    const btn = document.getElementById('generate-copy-btn');
    const productName = document.getElementById('product_name')?.value;
    if (!productName) {
        alert('Isi nama produk terlebih dahulu.');
        return;
    }

    const origText = btn.textContent;
    btn.textContent = '⏳ Generating...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('product_name', productName);
        const vibe = document.querySelector('[name=vibe]')?.value;
        if (vibe) formData.append('vibe', vibe);
        const audience = document.querySelector('[name=target_audience]')?.value;
        if (audience) formData.append('target_audience', audience);

        const res = await fetch('{{ route('metaadsimagegenerator.generate-copy') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData,
        });

        const json = await res.json();
        if (json.success && json.variations) {
            renderVariationCards(json.varations);
            btn.textContent = '✅ Generated!';
            setTimeout(() => { btn.textContent = origText; btn.disabled = false; }, 2000);
        } else {
            alert('Gagal generate copy: ' + (json.message || 'Unknown error'));
            btn.textContent = origText;
            btn.disabled = false;
        }
    } catch (e) {
        alert('Error: ' + e.message);
        btn.textContent = origText;
        btn.disabled = false;
    }
}

document.getElementById('generate-copy-btn')?.addEventListener('click', fetchVariations);
document.getElementById('regenerate-copy-btn')?.addEventListener('click', fetchVariations);
</script>
@endsection
