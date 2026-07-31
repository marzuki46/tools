@extends('layouts.app')

@section('title', 'Tools')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Available Tools</h1>
        <p class="text-gray-500 text-sm mt-1">Tools yang tersedia di platform. Hubungi admin untuk mengaktifkan akses.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
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
                    <div class="ml-4">
                        @if ($enabled)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                Active
                            </span>
                        @elseif ($tool->is_active)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                {{-- Inactive --}}
                            </span>
                        @else
                            <span class="text-xs text-gray-400 italic">Unavailable</span>
                        @endif
                    </div>
                </div>
                @if ($enabled && $tool->slug === 'meta-ads-generator')
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('metaadsimagegenerator.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Open Meta Ads Generator &rarr;
                        </a>
                    </div>
                @endif
                @if ($enabled && $tool->slug === 'keyword-research')
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('keywordresearch.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Open Keyword Research &rarr;
                        </a>
                    </div>
                @endif
                @if ($enabled && $tool->slug === 'content-generator')
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('contentgenerator.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Open Content Generator &rarr;
                        </a>
                    </div>
                @endif
                @if ($enabled && $tool->slug === 'schema-markup')
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('schema-markup.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Open Schema Markup Generator &rarr;
                        </a>
                    </div>
                @endif
                @if ($enabled && $tool->slug === 'seo-analyzer')
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('seo-analyzer.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Open SEO Analyzer &rarr;
                        </a>
                    </div>
                @endif
                @if ($enabled && $tool->slug === 'keyword-clusters')
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('seocluster.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Open Keyword Clusters &rarr;
                        </a>
                    </div>
                @endif
                @if ($enabled && $tool->slug === 'content-analyzer')
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('agentconnector.analyzer') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Open Content Analyzer &rarr;
                        </a>
                    </div>
                @endif
                @if ($enabled && $tool->slug === 'agent-connector')
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('agentconnector.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            Open Agent Connector &rarr;
                        </a>
                    </div>
                @endif
                @if (!$enabled && $tool->is_active)
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400">Tool ini belum aktif. Hubungi admin untuk aktivasi.</p>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
