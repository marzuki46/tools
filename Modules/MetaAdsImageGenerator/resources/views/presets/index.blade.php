@extends('layouts.app')

@section('title', 'Presets')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Presets</h1>
            <p class="text-gray-500 text-sm mt-1">Reusable style templates for ad generation</p>
        </div>
        <a href="{{ route('metaadsimagegenerator.presets.create') }}"
            class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + New Preset
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @forelse ($presets as $preset)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <h3 class="font-semibold">{{ $preset->name }}</h3>
                        @if ($preset->style_tag)
                            <span class="text-xs bg-purple-50 text-purple-600 px-2 py-0.5 rounded-full font-medium">{{ $preset->style_tag }}</span>
                        @endif
                        @if (is_null($preset->user_id))
                            <span class="text-xs bg-amber-50 text-amber-600 px-2 py-0.5 rounded-full font-medium">Global</span>
                        @endif
                    </div>
                    @if ($preset->user_id)
                        <div class="flex space-x-2">
                            <a href="{{ route('metaadsimagegenerator.presets.edit', $preset) }}" class="text-xs text-indigo-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('metaadsimagegenerator.presets.destroy', $preset) }}"
                                onsubmit="return confirm('Delete this preset?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                            </form>
                        </div>
                    @endif
                </div>
                <pre class="text-xs text-gray-500 bg-gray-50 p-3 rounded mt-2 overflow-x-auto">{{ $preset->prompt_template }}</pre>
            </div>
        @empty
            <div class="col-span-full text-center py-16 text-gray-400">
                <p>No presets yet.</p>
                <a href="{{ route('metaadsimagegenerator.presets.create') }}" class="text-indigo-600 text-sm hover:underline">Create your first preset</a>
            </div>
        @endforelse
    </div>

    {{ $presets->links() }}
</div>
@endsection
