@extends('layouts.app')

@section('title', 'My Websites')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">My Websites</h1>
            <p class="text-gray-500 text-sm mt-1">Register your domains to use Juki Tools</p>
        </div>
        <a href="{{ route('websites.create') }}"
            class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + Add Website
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($websites->count() > 0)
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr class="text-left text-gray-500">
                        <th class="px-6 py-3 font-medium">Domain</th>
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Active Tools</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Registered</th>
                        <th class="px-6 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($websites as $website)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-mono text-sm font-medium">{{ $website->domain }}</td>
                            <td class="px-6 py-4 text-gray-900">{{ $website->name }}</td>
                            <td class="px-6 py-4">
                                <span class="text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full text-xs font-medium">
                                    {{ $website->tools_count }} tools
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if ($website->is_verified)
                                    <span class="text-green-700 bg-green-50 px-2 py-0.5 rounded-full text-xs font-medium">Verified</span>
                                @else
                                    <span class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full text-xs font-medium">Unverified</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $website->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4">
                                <a href="{{ route('websites.show', $website) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Manage</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-16">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                </svg>
                <p class="text-gray-400 text-sm">No websites registered yet.</p>
                <a href="{{ route('websites.create') }}" class="inline-block mt-3 text-indigo-600 text-sm hover:underline">Register your first domain</a>
            </div>
        @endif
    </div>
</div>
@endsection
