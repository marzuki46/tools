@extends('layouts.app')

@section('title', $website->domain)

@section('content')
<div class="space-y-8">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('websites.index') }}" class="text-indigo-600 text-sm hover:underline">&larr; All Websites</a>
            <h1 class="text-2xl font-bold mt-1">{{ $website->domain }}</h1>
            <p class="text-gray-500 text-sm">{{ $website->name }}</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('websites.edit', $website) }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition">Edit</a>
            <form method="POST" action="{{ route('websites.destroy', $website) }}" class="inline"
                onsubmit="return confirm('Remove this website and all its tool keys?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-sm font-medium text-red-500 hover:text-red-700 transition">Remove</button>
            </form>
        </div>
    </div>

    @if (session('plain_text_key'))
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-sm font-medium text-amber-800 mb-1">Website Tool Key Generated!</p>
            <p class="text-xs text-amber-600 mb-2">Copy this key now. You won't be able to see it again.</p>
            <div class="flex items-center space-x-2">
                <code class="flex-1 p-2 bg-white border border-amber-300 rounded text-sm font-mono break-all">{{ session('plain_text_key') }}</code>
                <button onclick="navigator.clipboard.writeText('{{ session('plain_text_key') }}')"
                    class="text-amber-700 hover:text-amber-900 text-sm font-medium">Copy</button>
            </div>
            <div class="mt-3 p-3 bg-white rounded border border-amber-200">
                <p class="text-xs font-medium text-gray-700 mb-1">Embed this snippet in your website's &lt;head&gt;:</p>
                <code class="text-xs font-mono break-all bg-gray-50 p-2 block rounded">
                    &lt;script src="{{ request()->getSchemeAndHttpHost() }}/widget/TOOL_SLUG/{{ session('plain_text_key') }}.js"&gt;&lt;/script&gt;
                </code>
            </div>
        </div>
    @endif

    {{-- Tools Grid --}}
    <div>
        <h2 class="font-semibold text-lg mb-4">Tools</h2>
        @if ($website->description)
            <p class="text-gray-500 text-sm mb-4">{{ $website->description }}</p>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($allTools as $tool)
                @php
                    $pivot = $website->tools->firstWhere('id', $tool->id);
                    $enabled = !is_null($pivot);
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 {{ $enabled ? 'ring-1 ring-indigo-200' : 'opacity-75' }}">
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="font-medium text-gray-900">{{ $tool->name }}</h3>
                        <span class="text-xs {{ $enabled ? 'text-green-600' : 'text-gray-400' }}">
                            {{ $enabled ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    @if ($tool->description)
                        <p class="text-xs text-gray-500 mb-4">{{ $tool->description }}</p>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('websites.toggle-tool', [$website, $tool]) }}">
                            @csrf
                            <button type="submit" class="text-xs font-medium px-3 py-1.5 rounded-lg border transition
                                {{ $enabled ? 'text-red-600 border-red-200 hover:bg-red-50' : 'text-indigo-600 border-indigo-200 hover:bg-indigo-50' }}">
                                {{ $enabled ? 'Remove' : 'Add Tool' }}
                            </button>
                        </form>

                        @if ($enabled)
                            <form method="POST" action="{{ route('websites.generate-key', [$website, $tool]) }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
                                    {{ $pivot->pivot->api_key_hash ? 'Regenerate Key' : 'Generate Key' }}
                                </button>
                            </form>
                        @endif
                    </div>

                    @if ($enabled && $pivot->pivot->last_used_at)
                        <p class="text-xs text-gray-400 mt-3">Last used: {{ $pivot->pivot->last_used_at->diffForHumans() }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Status --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold mb-2">Status</h3>
        <div class="flex items-center space-x-2">
            @if ($website->is_verified)
                <span class="text-green-700 bg-green-50 px-2 py-0.5 rounded-full text-xs font-medium">Verified</span>
            @else
                <span class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full text-xs font-medium">Unverified</span>
            @endif
            @if ($website->is_active)
                <span class="text-green-700 bg-green-50 px-2 py-0.5 rounded-full text-xs font-medium">Active</span>
            @else
                <span class="text-red-700 bg-red-50 px-2 py-0.5 rounded-full text-xs font-medium">Suspended</span>
            @endif
        </div>
        @if ($website->verified_at)
            <p class="text-xs text-gray-400 mt-2">Verified {{ $website->verified_at->diffForHumans() }}</p>
        @endif
        <p class="text-xs text-gray-400 mt-1">Registered {{ $website->created_at->diffForHumans() }}</p>
    </div>
</div>
@endsection
