@extends('layouts.app')

@section('title', 'API Keys - ' . $user->name)

@section('content')
<div class="space-y-6">
    <div class="flex items-center space-x-3">
        <a href="{{ route('admin.users.edit', $user) }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold">API Keys — {{ $user->name }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ $user->email }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($keys->count() > 0)
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr class="text-left text-gray-500">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Prefix</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Last Used</th>
                        <th class="px-6 py-3 font-medium">Last IP</th>
                        <th class="px-6 py-3 font-medium">Expires</th>
                        <th class="px-6 py-3 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($keys as $key)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $key->name }}</td>
                            <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ $key->prefix }}...</td>
                            <td class="px-6 py-4">
                                @php $isActive = $key->is_active && (!$key->expires_at || $key->expires_at->isFuture()); @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $isActive ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ $key->last_ip ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->expires_at?->format('M d, Y') ?? 'Never' }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->created_at->format('M d, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">This user has no API keys.</p>
            </div>
        @endif
    </div>

    <div class="mt-4">{{ $keys->links() }}</div>
</div>
@endsection
