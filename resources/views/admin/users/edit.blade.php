@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.users.index') }}" class="text-indigo-600 text-sm hover:underline">&larr; Back to Users</a>
    </div>

    @if (session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <h1 class="text-2xl font-bold mb-2">Edit User</h1>
        <p class="text-gray-500 text-sm mb-8">Update user details and permissions</p>

        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-gray-400 font-normal">(leave empty to keep current)</span></label>
                <input id="password" type="password" name="password"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-6">
                <label class="flex items-center space-x-3">
                    <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin))
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Admin <span class="text-gray-400">(full access to manage users & settings)</span></span>
                </label>
            </div>

            <div class="flex items-center space-x-3">
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition shadow-sm">
                    Save Changes
                </button>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">Cancel</a>
            </div>
        </form>
    </div>

    {{-- Status --}}
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-semibold mb-3">Account Status</h2>
        @if ($user->suspended_at)
            <div class="flex items-center justify-between">
                <div>
                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-700">Suspended</span>
                    <p class="text-xs text-gray-400 mt-1">Since {{ $user->suspended_at->format('M d, Y H:i') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition shadow-sm">
                        Restore Access
                    </button>
                </form>
            </div>
        @else
            <div class="flex items-center justify-between">
                <div>
                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">Active</span>
                </div>
                @if ($user->id !== Auth::id())
                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700 transition shadow-sm">
                            Suspend User
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    {{-- Tool Access --}}
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-semibold mb-4">Tool Access</h2>
        <div class="space-y-2">
            @foreach ($tools as $tool)
                @php $active = $user->tools->contains($tool->id); @endphp
                <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-sm text-gray-900">{{ $tool->name }}</span>
                        <span class="text-xs font-mono text-gray-400">{{ $tool->slug }}</span>
                        @if ($active)
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Active</span>
                        @else
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-500">Inactive</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.users.toggle-tool', [$user, $tool]) }}">
                        @csrf
                        <button type="submit"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    {{-- API Keys Summary --}}
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold">API Keys</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $user->api_keys_count }} keys registered</p>
            </div>
            <a href="{{ route('admin.users.api-keys', $user) }}" class="text-indigo-600 text-sm hover:underline">View all</a>
        </div>
    </div>

    {{-- Danger Zone --}}
    @if ($user->id !== Auth::id())
        <div class="mt-6 bg-white rounded-xl shadow-sm border border-red-100 p-6">
            <h2 class="font-semibold text-red-600 mb-2">Danger Zone</h2>
            <p class="text-sm text-gray-500 mb-4">Permanently delete this user and all their data (API keys, tool access, generations).</p>
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete {{ $user->name }}? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition shadow-sm">
                    Permanently Delete
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
