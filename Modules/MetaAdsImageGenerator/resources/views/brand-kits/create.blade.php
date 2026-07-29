@extends('layouts.app')

@section('title', 'Create Brand Kit')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="{{ route('metaadsimagegenerator.brand-kits.index') }}" class="text-indigo-600 text-sm hover:underline">&larr; Back</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h1 class="text-2xl font-bold mb-2">Create Brand Kit</h1>
        <p class="text-gray-500 text-sm mb-6">Define your brand identity for ad creatives.</p>

        <form method="POST" action="{{ route('metaadsimagegenerator.brand-kits.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Brand Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Primary Color (hex)</label>
                <div class="flex items-center space-x-3">
                    <input type="color" name="primary_color" value="{{ old('primary_color', '#4F46E5') }}"
                        class="w-10 h-10 rounded border cursor-pointer">
                    <input type="text" name="primary_color_text" value="{{ old('primary_color', '#4F46E5') }}"
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                        placeholder="#4F46E5" maxlength="7"
                        oninput="this.form.primary_color.value=this.value; document.querySelector('input[name=primary_color]').value=this.value">
                </div>
                @error('primary_color')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Secondary Color (optional)</label>
                <div class="flex items-center space-x-3">
                    <input type="color" name="secondary_color" value="{{ old('secondary_color', '#000000') }}"
                        class="w-10 h-10 rounded border cursor-pointer">
                    <input type="text" name="secondary_color_text" value="{{ old('secondary_color', '#000000') }}"
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                        placeholder="#000000" maxlength="7">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Font Family (optional)</label>
                <input type="text" name="font_family" value="{{ old('font_family') }}" placeholder="e.g. Arial, Helvetica"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo (optional)</label>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg">
            </div>

            <div class="mb-6">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Set as default brand kit</span>
                </label>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Create Brand Kit
            </button>
        </form>
    </div>
</div>
@endsection
