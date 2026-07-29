@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">User Management</h1>
            <p class="text-gray-500 text-sm mt-1">Manage all registered users</p>
        </div>
        <div class="text-sm text-gray-500">{{ $users->total() }} total users</div>
    </div>

    <form method="GET" class="flex gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email..."
            class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        <button type="submit" class="px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Search</button>
        @if (request('search'))
            <a href="{{ route('admin.users.index') }}" class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 transition">Clear</a>
        @endif
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr class="text-left text-gray-500">
                    <th class="px-6 py-3 font-medium">Name</th>
                    <th class="px-6 py-3 font-medium">Email</th>
                    <th class="px-6 py-3 font-medium">Status</th>
                    <th class="px-6 py-3 font-medium">API Keys</th>
                    <th class="px-6 py-3 font-medium">Joined</th>
                    <th class="px-6 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($users as $user)
                    <tr class="hover:bg-gray-50 transition {{ $user->suspended_at ? 'opacity-75' : '' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-medium {{ $user->suspended_at ? 'text-gray-400' : 'text-indigo-600' }}">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <span class="font-medium {{ $user->suspended_at ? 'text-gray-400' : 'text-gray-900' }}">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 {{ $user->suspended_at ? 'text-gray-400' : 'text-gray-600' }}">{{ $user->email }}</td>
                        <td class="px-6 py-4">
                            @if ($user->suspended_at)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Suspended</span>
                            @elseif ($user->is_admin)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Admin</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.users.api-keys', $user) }}" class="text-indigo-600 hover:underline">{{ $user->api_keys_count }} keys</a>
                        </td>
                        <td class="px-6 py-4 {{ $user->suspended_at ? 'text-gray-400' : 'text-gray-500' }}">{{ $user->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:underline text-sm">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</div>
@endsection
