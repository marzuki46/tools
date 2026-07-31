@extends('layouts.app')

@section('title', 'Agent Connector')

@section('content')
<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-[calc(100vh-12rem)]">
            <div class="border-b border-gray-200 px-5 py-4 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm">🤖</div>
                    <div>
                        <h2 class="text-sm font-bold">Agent Connector</h2>
                        <p class="text-xs text-gray-500">Asisten SEO otomatis untuk riset, konten, dan publish</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">Online</span>
                    <button onclick="clearHistory()" title="Bersihkan riwayat chat"
                        class="px-2.5 py-1 text-xs font-medium rounded-full border border-gray-300 text-gray-500 hover:bg-gray-100 transition">🗑</button>
                </div>
            </div>

            <div id="chatMessages" class="flex-1 overflow-y-auto p-5 space-y-3 bg-gray-50 min-h-0">
                <div class="text-center py-8" id="emptyState">
                    <p class="text-sm font-medium text-gray-700 mb-1">Selamat datang di Agent Connector!</p>
                    <p class="text-xs text-gray-500">Saya bisa bantu kamu mengelola cluster keyword, riset, generate konten, analisa, dan publish.</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <span class="cursor-pointer px-3 py-1.5 text-xs font-medium rounded-full bg-white border border-gray-300 text-gray-700 hover:border-indigo-500 hover:text-indigo-600 transition" onclick="sendQuick('buat cluster')">buat cluster</span>
                        <span class="cursor-pointer px-3 py-1.5 text-xs font-medium rounded-full bg-white border border-gray-300 text-gray-700 hover:border-indigo-500 hover:text-indigo-600 transition" onclick="sendQuick('cluster saya')">cluster saya</span>
                        <span class="cursor-pointer px-3 py-1.5 text-xs font-medium rounded-full bg-white border border-gray-300 text-gray-700 hover:border-indigo-500 hover:text-indigo-600 transition" onclick="sendQuick('analisa konten')">analisa konten</span>
                        <span class="cursor-pointer px-3 py-1.5 text-xs font-medium rounded-full bg-white border border-gray-300 text-gray-700 hover:border-indigo-500 hover:text-indigo-600 transition" onclick="sendQuick('bantuan')">bantuan</span>
                    </div>
                </div>
            </div>

            <form id="chatForm" onsubmit="return sendMessage(event)" class="border-t border-gray-200 p-4 flex gap-3 bg-white flex-shrink-0">
                <input type="text" id="messageInput" placeholder="Ketik pesan..." autofocus
                    class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit" id="sendBtn" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition flex items-center gap-2">
                    Kirim
                </button>
            </form>
        </div>
    </div>

    <div class="col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-[calc(100vh-12rem)]">
            <div class="px-5 py-4 border-b border-gray-200 flex-shrink-0">
                <h3 class="text-sm font-bold">Tool Tersedia</h3>
                <p class="text-xs text-gray-500">Terintegrasi dengan Agent Connector</p>
            </div>
            <div id="toolList" class="divide-y divide-gray-100 overflow-y-auto flex-1 min-h-0">
                <div class="p-5 text-center text-sm text-gray-500">Memuat...</div>
            </div>
        </div>
    </div>
</div>

<script>
let pollTimers = [];

function escapeHtml(str) {
    return (str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function scrollToBottom() {
    const el = document.getElementById('chatMessages');
    el.scrollTop = el.scrollHeight;
}

function renderText(text) {
    return escapeHtml(text).replace(/\n/g, '<br>');
}

function addMessage(text, isUser = false) {
    document.getElementById('emptyState')?.remove();
    const div = document.createElement('div');
    div.className = `flex mb-2 ${isUser ? 'justify-end' : ''}`;
    div.innerHTML = `
        <div class="${isUser ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200'} rounded-xl px-4 py-2.5 text-sm shadow-sm" style="max-width: 85%; white-space: pre-wrap;">
            ${renderText(text)}
        </div>
    `;
    document.getElementById('chatMessages').appendChild(div);
    scrollToBottom();
    return div;
}

function addAssistantRow() {
    document.getElementById('emptyState')?.remove();
    const div = document.createElement('div');
    div.className = 'flex mb-2';
    div.innerHTML = `
        <div class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm shadow-sm w-full" style="max-width: 100%;">
            <div class="flex items-center gap-2 text-gray-500">
                <span class="inline-block w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin flex-shrink-0"></span>
                <span class="stage-text">Sedang memproses...</span>
            </div>
        </div>
    `;
    document.getElementById('chatMessages').appendChild(div);
    scrollToBottom();
    return div;
}

function toolCard(action) {
    const name = action?.tool || action?.action || 'tool';
    const status = action?.status || 'ok';
    const statusCls = status === 'ok' ? 'bg-green-100 text-green-700' : status === 'error' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600';
    const data = action?.data ?? null;
    let body = '';

    if (action?.message) {
        body += `<p class="text-xs text-gray-600 mb-2">${renderText(action.message)}</p>`;
    }
    if (data !== null) {
        body += `<pre class="text-[11px] bg-gray-900 text-green-300 rounded-lg p-3 overflow-x-auto font-mono whitespace-pre-wrap max-h-64 overflow-y-auto">${escapeHtml(JSON.stringify(data, null, 2))}</pre>`;
    }

    return `
        <div class="mt-2 rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-3 py-2 bg-gray-50 flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-700">🔧 ${escapeHtml(String(name).toUpperCase())}</span>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full ${statusCls}">${escapeHtml(String(status).toUpperCase())}</span>
            </div>
            <div class="p-3">${body || '<p class="text-xs text-gray-400">Tidak ada data hasil.</p>'}</div>
        </div>
    `;
}

function appendHistoryMessage(msg) {
    document.getElementById('emptyState')?.remove();
    const div = document.createElement('div');
    const isUser = msg.role === 'user';

    if (isUser) {
        div.className = 'flex mb-2 justify-end';
        div.innerHTML = `<div class="bg-indigo-600 text-white rounded-xl px-4 py-2.5 text-sm shadow-sm" style="max-width: 85%; white-space: pre-wrap;">${renderText(msg.content)}</div>`;
    } else {
        const toolCards = (msg.tool_data || []).map(toolCard).join('');
        const statusBadge = msg.status === 'error'
            ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-100 text-red-700">ERROR</span>' : '';
        div.className = 'flex mb-2';
        div.innerHTML = `
            <div class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm shadow-sm w-full" style="max-width: 100%;">
                ${statusBadge}
                <div style="white-space: pre-wrap;">${renderText(msg.content)}</div>
                ${toolCards}
            </div>
        `;
    }
    document.getElementById('chatMessages').appendChild(div);
}

function setBusy(busy) {
    document.getElementById('sendBtn').disabled = busy;
    document.getElementById('sendBtn').textContent = busy ? 'Memproses...' : 'Kirim';
    document.getElementById('messageInput').disabled = busy;
}

async function sendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('messageInput');
    const msg = input.value.trim();
    if (!msg) return;

    addMessage(msg, true);
    input.value = '';
    setBusy(true);

    try {
        const res = await fetch('{{ route('agentconnector.chat') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ message: msg })
        });
        const data = await res.json();
        const row = addAssistantRow();
        pollStatus(data.message_id, row);
    } catch (e) {
        addMessage('Gagal menghubungi server. Coba lagi.');
        setBusy(false);
    }
}

async function pollStatus(messageId, row) {
    try {
        const res = await fetch(`/agent-connector/chat/${messageId}/status`, { headers: { 'Accept': 'application/json' } });
        const msg = await res.json();

        const stageEl = row.querySelector('.stage-text');
        if (stageEl && (msg.status === 'queued' || msg.status === 'processing')) {
            stageEl.textContent = msg.stage || 'Sedang memproses...';
            setTimeout(() => pollStatus(messageId, row), 1500);
            return;
        }

        row.innerHTML = '';
        const statusBadge = msg.status === 'error'
            ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-100 text-red-700">ERROR</span>' : '';
        const toolCards = (msg.tool_data || []).map(toolCard).join('');
        row.innerHTML = `
            ${statusBadge}
            <div style="white-space: pre-wrap;">${renderText(msg.content)}</div>
            ${toolCards}
        `;
        scrollToBottom();
        setBusy(false);
    } catch (e) {
        row.querySelector('.stage-text').textContent = 'Gagal mengecek status. Muat ulang halaman untuk melihat hasil.';
        setBusy(false);
    }
}

function sendQuick(text) {
    if (document.getElementById('sendBtn').disabled) return;
    document.getElementById('messageInput').value = text;
    document.getElementById('chatForm').dispatchEvent(new Event('submit'));
}

async function loadHistory() {
    try {
        const res = await fetch('{{ route('agentconnector.history') }}', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        (data.data || []).forEach(appendHistoryMessage);
        scrollToBottom();
    } catch (e) {
        console.error('Gagal memuat riwayat chat', e);
    }
}

async function clearHistory() {
    if (!confirm('Hapus semua riwayat chat? Aksi ini tidak bisa dibatalkan.')) return;
    try {
        const res = await fetch('{{ route('agentconnector.clear') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('chatMessages').innerHTML = '';
            location.reload();
        }
    } catch (e) {
        alert('Gagal membersihkan riwayat.');
    }
}

async function loadTools() {
    try {
        const res = await fetch('/api/agent-connector/tools', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        const list = document.getElementById('toolList');
        if (data.data?.length) {
            list.innerHTML = data.data.map(t =>
                `<div class="p-4">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></span>
                        <strong class="text-sm text-gray-800">${escapeHtml(t.name)}</strong>
                    </div>
                    <div class="text-xs text-gray-500 mt-1 ml-4">${escapeHtml(t.description)}</div>
                </div>`
            ).join('');
        } else {
            list.innerHTML = '<div class="p-5 text-center text-sm text-gray-500">Sinkronisasi tool dulu</div>';
        }
    } catch (e) {
        document.getElementById('toolList').innerHTML = '<div class="p-5 text-center text-sm text-gray-500">Gagal memuat</div>';
    }
}

loadTools();
loadHistory();
</script>
<style>.cursor-pointer { cursor: pointer; }</style>
@endsection
