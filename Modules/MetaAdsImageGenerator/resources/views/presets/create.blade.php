@extends('layouts.app')

@section('title', 'Create Preset')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="{{ route('metaadsimagegenerator.presets.index') }}" class="text-indigo-600 text-sm hover:underline">&larr; Back</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h1 class="text-2xl font-bold mb-2">Create Preset</h1>
        <p class="text-gray-500 text-sm mb-6">Create a reusable prompt template.</p>

        <form method="POST" action="{{ route('metaadsimagegenerator.presets.store') }}">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Preset Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Style Tag (optional)</label>
                <input type="text" name="style_tag" value="{{ old('style_tag') }}" placeholder="e.g. minimalis, bold-promo, elegant"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Prompt Template</label>
                <p class="text-xs text-gray-400 mb-2">Use <code class="font-mono bg-gray-100 px-1 rounded">{product}</code>, <code class="font-mono bg-gray-100 px-1 rounded">{headline}</code>, <code class="font-mono bg-gray-100 px-1 rounded">{vibe}</code>, <code class="font-mono bg-gray-100 px-1 rounded">{notes}</code> as placeholders.</p>
                <textarea name="prompt_template" rows="6" required placeholder="A professional photo of {product}, {vibe} style, clean background"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm">{{ old('prompt_template') }}</textarea>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Create Preset
            </button>
        </form>
    </div>
</div>
@endsection
