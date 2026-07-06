@extends('layouts.app')

@section('title', 'API Keys')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">API Keys</h1>
            <p class="text-gray-500 text-sm mt-1">Manage your API access keys</p>
        </div>
        <button onclick="document.getElementById('createKeyModal').classList.remove('hidden')"
            class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            + Create Key
        </button>
    </div>

    @if (session('plain_text_key'))
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-sm font-medium text-amber-800 mb-1">API Key Created!</p>
            <p class="text-xs text-amber-600 mb-2">Copy this key now. You won't be able to see it again.</p>
            <div class="flex items-center space-x-2">
                <code class="flex-1 p-2 bg-white border border-amber-300 rounded text-sm font-mono break-all">{{ session('plain_text_key') }}</code>
                <button onclick="navigator.clipboard.writeText('{{ session('plain_text_key') }}')"
                    class="text-amber-700 hover:text-amber-900 text-sm font-medium">Copy</button>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if (count($keys) > 0)
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr class="text-left text-gray-500">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Last Used</th>
                        <th class="px-6 py-3 font-medium">IP</th>
                        <th class="px-6 py-3 font-medium">Expires</th>
                        <th class="px-6 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($keys as $key)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-medium">{{ $key->name }}</td>
                            <td class="px-6 py-4">
                                @if ($key->is_active && (!$key->expires_at || $key->expires_at->isFuture()))
                                    <span class="text-green-700 bg-green-50 px-2 py-0.5 rounded-full text-xs font-medium">Active</span>
                                @else
                                    <span class="text-red-700 bg-red-50 px-2 py-0.5 rounded-full text-xs font-medium">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never' }}</td>
                            <td class="px-6 py-4 text-gray-500 font-mono text-xs">{{ $key->last_ip ?? '-' }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->expires_at ? $key->expires_at->format('M d, Y') : 'Never' }}</td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('api-keys.destroy', $key) }}" class="inline"
                                    onsubmit="return confirm('Revoke this API key? This cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-gray-400 text-sm py-12 text-center">No API keys yet. Create your first key to get started.</p>
        @endif
    </div>
</div>

{{-- Create Key Modal --}}
<div id="createKeyModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold mb-2">Create API Key</h2>
        <p class="text-gray-500 text-sm mb-6">Give your key a name to identify it later.</p>
        <form method="POST" action="{{ route('api-keys.store') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Key Name</label>
                <input type="text" name="name" required placeholder="e.g. Production, Development"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Expires At (optional)</label>
                <input type="date" name="expires_at"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="this.closest('#createKeyModal').classList.add('hidden')"
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 hover:text-gray-900 transition">Cancel</button>
                <button type="submit"
                    class="px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">Create Key</button>
            </div>
        </form>
    </div>
</div>
@endsection
