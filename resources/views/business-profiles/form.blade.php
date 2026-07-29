@php $isEdit = isset($profile); @endphp

<form action="{{ $isEdit ? route('business-profiles.update', $profile->id) : route('business-profiles.store') }}" method="POST" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Profil *</label>
            <input type="text" name="name" required placeholder="Contoh: Website Utama, Toko Online" value="{{ old('name', $profile->name ?? '') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <p class="text-xs text-gray-400 mt-1">Nama untuk membedakan profil (misal: Website A, Toko B)</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bisnis</label>
            <input type="text" name="business_name" placeholder="Nama perusahaan/toko" value="{{ old('business_name', $profile->business_name ?? '') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Website URL</label>
            <input type="url" name="website_url" placeholder="https://example.com" value="{{ old('website_url', $profile->website_url ?? '') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Bisnis</label>
        <textarea name="description" rows="3" placeholder="Jelaskan apa yang dilakukan bisnis Anda..." 
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $profile->description ?? '') }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Produk / Jasa</label>
        <textarea name="products_services" rows="2" placeholder="Apa saja produk atau jasa yang dijual? Pisahkan dengan koma atau baris baru"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('products_services', $profile->products_services ?? '') }}</textarea>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Target Audiens</label>
            <input type="text" name="target_audience" placeholder="Contoh: UMKM, ibu rumah tangga, pebisnis online" value="{{ old('target_audience', $profile->target_audience ?? '') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Jam Operasional</label>
            <input type="text" name="business_hours" placeholder="Contoh: Senin-Jumat 08:00-17:00" value="{{ old('business_hours', $profile->business_hours ?? '') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Keunggulan (USP)</label>
        <textarea name="usp" rows="2" placeholder="Apa yang membedakan bisnis Anda dari kompetitor?"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('usp', $profile->usp ?? '') }}</textarea>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email Kontak</label>
            <input type="email" name="contact_email" placeholder="email@example.com" value="{{ old('contact_email', $profile->contact_email ?? '') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Telepon</label>
            <input type="text" name="contact_phone" placeholder="0812-xxxx-xxxx" value="{{ old('contact_phone', $profile->contact_phone ?? '') }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
        <input type="text" name="address" placeholder="Alamat lengkap" value="{{ old('address', $profile->address ?? '') }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Sosial Media <span class="text-gray-400 font-normal">(opsional)</span></label>
        @php $sm = old('social_media', $profile->social_media ?? []); @endphp
        <div class="grid grid-cols-2 gap-3">
            @foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'twitter' => 'Twitter / X', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn'] as $key => $label)
            <div>
                <label class="text-xs text-gray-500">{{ $label }}</label>
                <input type="text" name="social_media[{{ $key }}]" placeholder="@username" value="{{ $sm[$key] ?? '' }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_default" value="1" {{ old('is_default', $profile->is_default ?? false) ? 'checked' : '' }}
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-gray-700">Jadikan profil default</span>
        </label>
    </div>

    <div class="pt-2 border-t border-gray-100 flex gap-3">
        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Profil' }}
        </button>
        <a href="{{ route('business-profiles.index') }}" class="text-gray-500 hover:text-gray-700 text-sm font-medium px-4 py-2">Batal</a>
    </div>
</form>
