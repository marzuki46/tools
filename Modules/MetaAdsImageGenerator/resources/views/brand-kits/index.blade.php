@extends('layouts.app')

@section('title', 'Brand Kits')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Brand Kits</h1>
            <p class="text-gray-500 text-sm mt-1">Manage your brand colors, logos, and fonts</p>
        </div>
        <a href="{{ route('metaadsimagegenerator.brand-kits.create') }}"
            class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + New Brand Kit
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @forelse ($brandKits as $kit)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 {{ $kit->is_default ? 'ring-2 ring-indigo-300' : '' }}">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="font-semibold">{{ $kit->name }}</h3>
                        @if ($kit->is_default)
                            <span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full font-medium">Default</span>
                        @endif
                    </div>
                    <div class="flex space-x-2">
                        <a href="{{ route('metaadsimagegenerator.brand-kits.edit', $kit) }}"
                            class="text-xs text-indigo-600 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('metaadsimagegenerator.brand-kits.destroy', $kit) }}"
                            onsubmit="return confirm('Delete this brand kit?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                        </form>
                    </div>
                </div>

                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 rounded-full border" style="background: {{ $kit->primary_color }}"></div>
                    @if ($kit->secondary_color)
                        <div class="w-8 h-8 rounded-full border" style="background: {{ $kit->secondary_color }}"></div>
                    @endif
                    <span class="text-xs text-gray-500 font-mono">{{ $kit->primary_color }}</span>
                </div>

                @if ($kit->font_family)
                    <p class="text-xs text-gray-500">Font: <span class="font-mono">{{ $kit->font_family }}</span></p>
                @endif
                @if ($kit->logo_path)
                    <p class="text-xs text-green-600 mt-1">Logo uploaded</p>
                @endif
            </div>
        @empty
            <div class="col-span-full text-center py-16 text-gray-400">
                <p>No brand kits yet.</p>
                <a href="{{ route('metaadsimagegenerator.brand-kits.create') }}" class="text-indigo-600 text-sm hover:underline">Create your first brand kit</a>
            </div>
        @endforelse
    </div>

    {{ $brandKits->links() }}
</div>
@endsection
