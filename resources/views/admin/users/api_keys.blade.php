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
                        <th class="px-6 py-3 font-medium">Email</th>
                        <th class="px-6 py-3 font-medium">Key</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Max Sites</th>
                        <th class="px-6 py-3 font-medium">Last Used</th>
                        <th class="px-6 py-3 font-medium">Expires</th>
                        <th class="px-6 py-3 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($keys as $key)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $key->name }}</td>
                            <td class="px-6 py-4 text-gray-500 text-xs">{{ $key->user->email }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <code class="text-xs font-mono text-gray-500" id="akey-display-{{ $key->id }}">
                                        <span class="akey-masked">•••••••••••••••••••••••••••••</span>
                                        <span class="akey-full hidden"></span>
                                    </code>
                                    <button type="button" onclick="aToggleKey({{ $key->id }})" class="text-gray-400 hover:text-gray-600" title="Show/Hide key">
                                        <svg class="w-4 h-4" id="aeye-icon-{{ $key->id }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg class="w-4 h-4 hidden" id="aeye-off-icon-{{ $key->id }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $status = $key->status;
                                    $colors = ['active' => 'bg-green-100 text-green-700', 'suspended' => 'bg-red-100 text-red-700', 'expired' => 'bg-amber-100 text-amber-700'];
                                    $label = $colors[$status] ?? 'bg-gray-100 text-gray-500';
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $label }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->max_sites ?? '∞' }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td>
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

@push('scripts')
<script>
let aKeyCache = {};

function aFetchKey(id) {
    if (aKeyCache[id]) return Promise.resolve(aKeyCache[id]);
    return fetch('/api-keys/' + id + '/key')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                aKeyCache[id] = data.key;
                return data.key;
            }
            throw new Error(data.message || 'Could not retrieve key.');
        });
}

function aToggleKey(id) {
    const fullEl = document.querySelector(`#akey-display-${id} .akey-full`);
    const maskedEl = document.querySelector(`#akey-display-${id} .akey-masked`);
    const eyeIcon = document.getElementById('aeye-icon-' + id);
    const eyeOffIcon = document.getElementById('aeye-off-icon-' + id);

    if (fullEl.classList.contains('hidden')) {
        aFetchKey(id).then(key => {
            fullEl.textContent = key;
            fullEl.classList.remove('hidden');
            maskedEl.classList.add('hidden');
            eyeIcon.classList.add('hidden');
            eyeOffIcon.classList.remove('hidden');
        }).catch(err => alert(err.message));
    } else {
        fullEl.classList.add('hidden');
        maskedEl.classList.remove('hidden');
        eyeIcon.classList.remove('hidden');
        eyeOffIcon.classList.add('hidden');
    }
}
</script>
@endpush
