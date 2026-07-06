@extends('layouts.app')

@section('title', 'Tools')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Available Tools</h1>
        <p class="text-gray-500 text-sm mt-1">Enable tools you want to use in your account</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($tools as $tool)
            @php $enabled = in_array($tool->id, $userToolIds); @endphp
            <div class="bg-white rounded-xl shadow-sm border {{ $enabled ? 'border-indigo-200' : 'border-gray-200' }} p-6 transition hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-2">
                            <h3 class="font-semibold text-gray-900">{{ $tool->name }}</h3>
                            @if ($tool->is_active)
                                <span class="text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full font-medium">Available</span>
                            @else
                                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-medium">Coming Soon</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ $tool->description }}</p>
                        @if ($tool->package_name)
                            <p class="text-xs text-gray-400 mt-2 font-mono">{{ $tool->package_name }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('tools.toggle', $tool) }}" class="ml-4">
                        @csrf
                        @if ($tool->is_active)
                            <button type="submit"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $enabled ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $enabled ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        @else
                            <span class="text-xs text-gray-400 italic">Unavailable</span>
                        @endif
                    </form>
                </div>
                @if ($enabled && $tool->slug === 'meta-ads-generator')
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="#" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Open Meta Ads Generator &rarr;
                        </a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
