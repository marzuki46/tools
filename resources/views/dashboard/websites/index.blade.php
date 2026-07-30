@extends('layouts.app')

@section('title', 'Websites')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Websites</h1>
            <p class="text-gray-500 text-sm mt-1">{{ auth()->user()->email }} — Domains registered via API keys</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($websites->count() > 0)
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr class="text-left text-gray-500">
                        <th class="px-6 py-3 font-medium">Domain</th>
                        <th class="px-6 py-3 font-medium">API Key</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Last Used</th>
                        <th class="px-6 py-3 font-medium">Last IP</th>
                        <th class="px-6 py-3 font-medium">Tokens</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($websites as $site)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-mono text-sm font-medium">{{ $site->domain }}</td>
                            <td class="px-6 py-4 text-xs text-gray-500">{{ $site->apiKey?->name ?? 'N/A' }}</td>
                            <td class="px-6 py-4">
                                @if ($site->is_active)
                                    <span class="text-green-700 bg-green-50 px-2 py-0.5 rounded-full text-xs font-medium">Active</span>
                                @else
                                    <span class="text-red-700 bg-red-50 px-2 py-0.5 rounded-full text-xs font-medium">Blocked</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs">{{ $site->last_used_at ? $site->last_used_at->diffForHumans() : 'Never' }}</td>
                            <td class="px-6 py-4 text-gray-500 text-xs font-mono">{{ $site->last_ip ?? '-' }}</td>
                            <td class="px-6 py-4 text-xs text-gray-500">
                                {{ number_format($site->tokens_total) }} total
                                <span class="text-gray-400">({{ number_format($site->tokens_in) }} in / {{ number_format($site->tokens_out) }} out)</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-16">
                <p class="text-gray-400 text-sm">No websites registered yet.</p>
                <p class="text-gray-400 text-xs mt-2">Websites will appear here automatically when API keys are used.</p>
            </div>
        @endif
    </div>

    @if ($websites->hasPages())
        <div class="mt-4">{{ $websites->links() }}</div>
    @endif
</div>
@endsection