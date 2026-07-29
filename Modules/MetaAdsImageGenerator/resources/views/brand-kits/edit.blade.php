@extends('layouts.app')

@section('title', 'Edit Brand Kit')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="{{ route('metaadsimagegenerator.brand-kits.index') }}" class="text-indigo-600 text-sm hover:underline">&larr; Back</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h1 class="text-2xl font-bold mb-2">Edit Brand Kit</h1>

        <form method="POST" action="{{ route('metaadsimagegenerator.brand-kits.update', $brandKit) }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Brand Name</label>
                <input type="text" name="name" value="{{ old('name', $brandKit->name) }}" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Primary Color</label>
                <div class="flex items-center space-x-3">
                    <input type="color" value="{{ old('primary_color', $brandKit->primary_color) }}"
                        class="w-10 h-10 rounded border cursor-pointer"
                        oninput="this.form.primary_color.value=this.value">
                    <input type="text" name="primary_color" value="{{ old('primary_color', $brandKit->primary_color) }}" required maxlength="7"
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Secondary Color (optional)</label>
                <div class="flex items-center space-x-3">
                    <input type="color" value="{{ old('secondary_color', $brandKit->secondary_color ?? '#000000') }}"
                        class="w-10 h-10 rounded border cursor-pointer"
                        oninput="this.form.secondary_color.value=this.value">
                    <input type="text" name="secondary_color" value="{{ old('secondary_color', $brandKit->secondary_color) }}" maxlength="7"
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Font Family</label>
                <input type="text" name="font_family" value="{{ old('font_family', $brandKit->font_family) }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                @if ($brandKit->logo_path)
                    <p class="text-xs text-green-600 mb-2">Current logo uploaded.</p>
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg">
            </div>

            <div class="mb-6">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_default" value="1" {{ $brandKit->is_default ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Set as default brand kit</span>
                </label>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Update Brand Kit
            </button>
        </form>
    </div>
</div>
@endsection
