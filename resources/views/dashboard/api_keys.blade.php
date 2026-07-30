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
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg" id="new-key-notice">
            <p class="text-sm font-medium text-amber-800 mb-1">API Key Created!</p>
            <p class="text-xs text-amber-600 mb-2">Copy this key now. You won't be able to see it again.</p>
            <div class="flex items-center space-x-2">
                <code class="flex-1 p-2 bg-white border border-amber-300 rounded text-sm font-mono break-all">{{ session('plain_text_key') }}</code>
                <button onclick="navigator.clipboard.writeText('{{ session('plain_text_key') }}');this.textContent='Copied!'"
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
                        <th class="px-6 py-3 font-medium">Key</th>
                        <th class="px-6 py-3 font-medium">Sites</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Last Used</th>
                        <th class="px-6 py-3 font-medium">Expires</th>
                        <th class="px-6 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($keys as $key)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <span class="font-medium">{{ $key->name }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <code class="text-xs font-mono text-gray-500">{{ $key->suffix }}</code>
                                    <button onclick="copyKey({{ $key->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium" title="Copy full key">Copy</button>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs text-gray-500">
                                    {{ $key->websites_count }}{{ $key->max_sites ? '/' . $key->max_sites : '' }} sites
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $status = $key->status;
                                    $colors = ['active' => 'text-green-700 bg-green-50', 'suspended' => 'text-red-700 bg-red-50', 'expired' => 'text-amber-700 bg-amber-50'];
                                    $label = $colors[$status] ?? 'text-gray-700 bg-gray-50';
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $label }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never' }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->expires_at ? $key->expires_at->format('M d, Y') : 'Never' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <button onclick="openEditModal({{ $key->id }}, '{{ $key->name }}', {{ $key->max_sites ?? 'null' }})"
                                        class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</button>
                                    @if ($key->is_active)
                                        <form method="POST" action="{{ route('api-keys.suspend', $key) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-amber-600 hover:text-amber-800 text-xs font-medium">Suspend</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('api-keys.unsuspend', $key) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-green-600 hover:text-green-800 text-xs font-medium">Reactivate</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('api-keys.destroy', $key) }}" class="inline"
                                        onsubmit="return confirm('Revoke this API key? This cannot be undone.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Revoke</button>
                                    </form>
                                </div>
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
        <p class="text-gray-500 text-sm mb-6">Give your key a name and set usage limits.</p>
        <form method="POST" action="{{ route('api-keys.store') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Key Name</label>
                <input type="text" name="name" required placeholder="e.g. Production, Development"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Websites (optional)</label>
                <input type="number" name="max_sites" min="1" max="1000" placeholder="Leave empty for unlimited"
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

{{-- Edit Key Modal --}}
<div id="editKeyModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold mb-2">Edit API Key</h2>
        <form method="POST" action="" id="editKeyForm">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Key Name</label>
                <input type="text" name="name" id="edit_name" required
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Websites</label>
                <input type="number" name="max_sites" id="edit_max_sites" min="1" max="1000" placeholder="Unlimited"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="this.closest('#editKeyModal').classList.add('hidden')"
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 hover:text-gray-900 transition">Cancel</button>
                <button type="submit"
                    class="px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">Save</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openEditModal(id, name, maxSites) {
    document.getElementById('editKeyForm').action = '{{ url('api-keys') }}/' + id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_max_sites').value = maxSites || '';
    document.getElementById('editKeyModal').classList.remove('hidden');
}

function copyKey(id) {
    fetch('{{ url('api-keys') }}/' + id + '/key')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                navigator.clipboard.writeText(data.key).then(() => {
                    alert('API key copied to clipboard!');
                });
            } else {
                alert(data.message || 'Could not retrieve key.');
            }
        })
        .catch(() => alert('Failed to retrieve key.'));
}
</script>
@endpush
@endsection
