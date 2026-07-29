@extends('layouts.app')

@section('title', 'Edit Preset')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="{{ route('metaadsimagegenerator.presets.index') }}" class="text-indigo-600 text-sm hover:underline">&larr; Back</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h1 class="text-2xl font-bold mb-2">Edit Preset</h1>

        <form method="POST" action="{{ route('metaadsimagegenerator.presets.update', $preset) }}">
            @csrf @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Preset Name</label>
                <input type="text" name="name" value="{{ old('name', $preset->name) }}" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Style Tag</label>
                <input type="text" name="style_tag" value="{{ old('style_tag', $preset->style_tag) }}"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Prompt Template</label>
                <textarea name="prompt_template" rows="6" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm">{{ old('prompt_template', $preset->prompt_template) }}</textarea>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Update Preset
            </button>
        </form>
    </div>
</div>
@endsection
