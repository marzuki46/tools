@extends('layouts.app')

@section('title', 'API Keys')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">API Keys</h1>
            <p class="text-gray-500 text-sm mt-1">{{ auth()->user()->email }} — Manage your API access keys</p>
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
                        <th class="px-6 py-3 font-medium">Email</th>
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
                            <td class="px-6 py-4 text-gray-500 text-xs">{{ auth()->user()->email }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <code class="text-xs font-mono text-gray-500">{{ $key->key_prefix }}</code>
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
                                    <button onclick="showDetail({{ $key->id }})"
                                        class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Detail</button>
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

{{-- Detail Key Modal --}}
<div id="detailKeyModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold" id="detail-name"></h2>
                <p class="text-gray-500 text-sm" id="detail-email">{{ auth()->user()->email }}</p>
            </div>
            <button type="button" onclick="document.getElementById('detailKeyModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Status</div>
                <div id="detail-status" class="font-semibold"></div>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Expires</div>
                <div id="detail-expires" class="font-semibold"></div>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Total Token Usage</div>
                <div id="detail-tokens" class="font-semibold"></div>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Max Sites</div>
                <div id="detail-max-sites" class="font-semibold"></div>
            </div>
        </div>

        <div class="mb-4">
            <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-gray-700">API Key</label>
                <div class="flex items-center space-x-2">
                    <button onclick="dToggleKey()" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Show/Hide</button>
                    <button onclick="dCopyKey()" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Copy</button>
                </div>
            </div>
            <code id="detail-key" class="block mt-1 p-2 bg-gray-50 border rounded text-xs font-mono break-all select-all">•••••••••••••••••••••••••••••</code>
        </div>

        <div class="mb-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Websites</h3>
                <span id="detail-websites-count" class="text-xs text-gray-400"></span>
            </div>
            <div id="detail-websites" class="mt-2 space-y-2"></div>
        </div>
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
let detailKeyCache = null;

function openEditModal(id, name, maxSites) {
    document.getElementById('editKeyForm').action = '{{ url('api-keys') }}/' + id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_max_sites').value = maxSites || '';
    document.getElementById('editKeyModal').classList.remove('hidden');
}

function copyKey(id) {
    fetch('{{ url('api-keys') }}/' + id + '/key')
        .then(function (r) {
            if (!r.ok) throw new Error('Server error. Run git pull on the server.');
            return r.json();
        })
        .then(function (data) {
            if (data.success) {
                navigator.clipboard.writeText(data.key).then(function () {
                    alert('API key copied to clipboard!');
                });
            } else {
                alert(data.message || 'Could not retrieve key.');
            }
        })
        .catch(function (err) { alert(err.message); });
}

// ── Detail Modal ──

function showDetail(id) {
    var modal = document.getElementById('detailKeyModal');
    modal.classList.remove('hidden');
    detailKeyCache = null;
    document.getElementById('detail-key').textContent = '•••••••••••••••••••••••••••••';
    document.getElementById('detail-name').textContent = 'Loading...';
    document.getElementById('detail-websites').innerHTML = '<div class="text-gray-400 text-sm py-4 text-center">Loading...</div>';

    fetch('{{ url('api-keys') }}/' + id + '/detail')
        .then(function (r) {
            if (!r.ok) throw new Error('Server error. Run git pull on the server.');
            return r.json();
        })
        .then(function (resp) {
            if (!resp.success) throw new Error(resp.message || 'Failed to load.');
            var d = resp.data;
            detailKeyCache = d.key;
            document.getElementById('detail-name').textContent = d.name + ' (' + d.status + ')';
            document.getElementById('detail-key').textContent = d.key;
            document.getElementById('detail-status').innerHTML = '<span class="px-2 py-0.5 rounded-full text-xs font-medium ' + (d.is_active ? 'text-green-700 bg-green-50' : 'text-red-700 bg-red-50') + '">' + d.status.charAt(0).toUpperCase() + d.status.slice(1) + '</span>';
            document.getElementById('detail-expires').textContent = d.expires_at || 'Never';
            document.getElementById('detail-tokens').textContent = (d.total_tokens || 0).toLocaleString() + ' (' + (d.total_tokens_in || 0).toLocaleString() + ' in / ' + (d.total_tokens_out || 0).toLocaleString() + ' out)';
            document.getElementById('detail-max-sites').textContent = d.max_sites || 'Unlimited';
            document.getElementById('detail-websites-count').textContent = (d.websites ? d.websites.length : 0) + ' sites';

            var html = '';
            (d.websites || []).forEach(function (w) {
                var statusClass = w.is_active ? 'text-green-700 bg-green-50' : 'text-red-700 bg-red-50';
                var statusText = w.is_active ? 'Active' : 'Blocked';
                html += '<div class="p-3 border rounded-lg">';
                html += '<div class="flex items-center justify-between">';
                html += '<div><div class="font-medium text-sm">' + escHtml(w.domain) + '</div>';
                html += '<div class="text-xs text-gray-400">IP: ' + escHtml(w.last_ip || '-') + ' &middot; Last used: ' + (w.last_used_at || 'Never') + '</div></div>';
                html += '<span class="px-2 py-0.5 rounded-full text-xs font-medium ' + statusClass + '">' + statusText + '</span>';
                html += '</div>';
                html += '<div class="mt-2 grid grid-cols-3 gap-2 text-xs">';
                html += '<div><span class="text-gray-500">Tokens:</span> ' + (w.tokens_total || 0).toLocaleString() + '</div>';
                html += '<div><span class="text-gray-500">Content:</span> ' + (w.content_generations || 0) + '</div>';
                html += '<div><span class="text-gray-500">Keywords:</span> ' + (w.keyword_researches || 0) + '</div>';
                html += '</div></div>';
            });
            if (!d.websites || !d.websites.length) {
                html = '<div class="text-gray-400 text-sm py-4 text-center">No websites registered yet.</div>';
            }
            document.getElementById('detail-websites').innerHTML = html;
        })
        .catch(function (err) {
            document.getElementById('detail-name').textContent = 'Error';
            document.getElementById('detail-websites').innerHTML = '<div class="text-red-500 text-sm py-4 text-center">' + err.message + '</div>';
        });
}

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}
</script>
@endpush
@endsection
