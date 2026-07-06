@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="text-gray-500 text-sm mt-1">Welcome back, {{ Auth::user()->name }}!</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="text-3xl font-bold text-indigo-600">{{ $stats['total_keys'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Total API Keys</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="text-3xl font-bold text-green-600">{{ $stats['active_keys'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Active Keys</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="text-3xl font-bold text-amber-600">{{ $stats['tools_count'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Available Tools</div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-lg font-semibold mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="{{ route('api-keys.index') }}" class="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition group">
                <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-200 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
                <div>
                    <div class="font-medium text-gray-900">Manage API Keys</div>
                    <div class="text-xs text-gray-500">Create and revoke access keys</div>
                </div>
            </a>
            <div class="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 opacity-50 cursor-not-allowed">
                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <div class="font-medium text-gray-500">Tool Settings</div>
                    <div class="text-xs text-gray-400">Coming soon</div>
                </div>
            </div>
            <div class="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 opacity-50 cursor-not-allowed">
                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div>
                    <div class="font-medium text-gray-500">Analytics</div>
                    <div class="text-xs text-gray-400">Coming soon</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent API Keys --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">Recent API Keys</h2>
            <a href="{{ route('api-keys.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">View All</a>
        </div>
        @if (count($recent_keys) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="pb-3 font-medium">Name</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium">Last Used</th>
                            <th class="pb-3 font-medium">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent_keys as $key)
                            <tr class="border-b last:border-0">
                                <td class="py-3 font-medium">{{ $key->name }}</td>
                                <td class="py-3">
                                    @if ($key->is_active && (!$key->expires_at || $key->expires_at->isFuture()))
                                        <span class="text-green-600 bg-green-50 px-2 py-0.5 rounded-full text-xs font-medium">Active</span>
                                    @else
                                        <span class="text-red-600 bg-red-50 px-2 py-0.5 rounded-full text-xs font-medium">Inactive</span>
                                    @endif
                                </td>
                                <td class="py-3 text-gray-500">{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never' }}</td>
                                <td class="py-3 text-gray-500">{{ $key->created_at->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-400 text-sm py-4 text-center">No API keys yet. Create your first key!</p>
        @endif
    </div>
</div>
@endsection
