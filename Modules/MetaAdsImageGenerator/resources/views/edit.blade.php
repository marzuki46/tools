@extends('layouts.app')

@section('title', 'Edit Generation')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('metaadsimagegenerator.show', $generation->id) }}" class="text-indigo-600 text-sm hover:underline">&larr; Back</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h1 class="text-2xl font-bold mb-2">Edit Generation</h1>
        <p class="text-gray-500 text-sm mb-8">Update your generation settings</p>

        <form method="POST" action="{{ route('metaadsimagegenerator.update', $generation->id) }}">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <select name="project_id" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected($generation->project_id === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Preset</label>
                    <select name="preset_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">No preset</option>
                        @foreach ($presets as $preset)
                            <option value="{{ $preset->id }}" @selected($generation->preset_id === $preset->id)>{{ $preset->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Brand Kit</label>
                    <select name="brand_kit_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">No brand kit</option>
                        @foreach ($brandKits as $kit)
                            <option value="{{ $kit->id }}" @selected($generation->project->brand_kit_id === $kit->id)>{{ $kit->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                    <input type="text" name="product_name" value="{{ old('product_name', $generation->input_form['product_name'] ?? '') }}" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Headline</label>
                    <input type="text" name="headline" value="{{ old('headline', $generation->input_form['headline'] ?? '') }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CTA</label>
                    <input type="text" name="cta" value="{{ old('cta', $generation->input_form['cta'] ?? '') }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vibe</label>
                    <select name="vibe"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select...</option>
                        @foreach (['minimalis', 'bold-promo', 'elegant', 'playful', 'professional', 'luxury'] as $v)
                            <option value="{{ $v }}" @selected(($generation->input_form['vibe'] ?? '') === $v)>{{ ucfirst($v) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes', $generation->input_form['notes'] ?? '') }}</textarea>
            </div>

            <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg font-medium hover:bg-indigo-700 transition shadow-sm">
                Update Generation
            </button>
        </form>
    </div>
</div>
@endsection
