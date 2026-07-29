@extends('layouts.app')

@section('title', 'Profil Bisnis')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Profil Bisnis</h1>
            <p class="text-gray-500 text-sm mt-1">Informasi bisnis/website Anda yang akan disisipkan ke konten AI</p>
        </div>
        <a href="{{ route('business-profiles.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + Profil Baru
        </a>
    </div>

    <div class="bg-indigo-50 border border-indigo-200 p-5 rounded-xl text-sm text-indigo-700">
        <strong>Apa ini?</strong> Simpan informasi bisnis/website Anda di sini (nama bisnis, produk/jasa, keunggulan, kontak).
        Saat membuat konten di Content Generator, AI akan secara otomatis memasukkan informasi ini ke dalam artikel yang dihasilkan.
        Cocok untuk SEO lokal, konten marketing, dan branding.
    </div>

    <div class="grid gap-4">
        @forelse ($profiles as $profile)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 {{ $profile->is_default ? 'ring-2 ring-indigo-300' : '' }}">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-lg">{{ $profile->name }}</h3>
                        @if ($profile->is_default)
                        <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-medium">Default</span>
                        @endif
                    </div>
                    @if ($profile->business_name)
                    <p class="text-gray-700 mt-1">{{ $profile->business_name }}</p>
                    @endif
                    @if ($profile->website_url)
                    <p class="text-gray-400 text-sm mt-0.5">{{ $profile->website_url }}</p>
                    @endif
                    @if ($profile->description)
                    <p class="text-gray-500 text-sm mt-2 line-clamp-2">{{ Str::limit($profile->description, 200) }}</p>
                    @endif
                    @if ($profile->products_services)
                    <div class="mt-2">
                        <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Produk/Jasa</span>
                        <p class="text-sm text-gray-600">{{ Str::limit($profile->products_services, 150) }}</p>
                    </div>
                    @endif
                </div>
                <div class="flex gap-2 ml-4">
                    <a href="{{ route('business-profiles.edit', $profile->id) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Edit</a>
                    <form action="{{ route('business-profiles.destroy', $profile->id) }}" method="POST" onsubmit="return confirm('Hapus profil ini?')">
                        @csrf @method('DELETE')
                        <button class="text-red-500 hover:text-red-700 text-sm font-medium">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-gray-500">
            <p class="text-lg mb-1">Belum ada profil bisnis</p>
            <p class="text-sm">Buat profil pertama untuk mulai menyisipkan informasi bisnis ke konten AI.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
