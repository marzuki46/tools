@extends('layouts.app')

@section('title', 'Agent Connector')

@section('content')
<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 flex flex-col">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
            <div class="border-b border-gray-200 px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm">🤖</div>
                    <div>
                        <h2 class="text-sm font-bold">Agent Connector</h2>
                        <p class="text-xs text-gray-500">Asisten SEO otomatis untuk riset, konten, dan publish</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">Online</span>
            </div>

            <div id="chatMessages" class="flex-1 h-[520px] overflow-y-auto p-5 space-y-3 bg-gray-50">
                <div class="text-center py-8">
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

            <form id="chatForm" onsubmit="return sendMessage(event)" class="border-t border-gray-200 p-4 flex gap-3 bg-white">
                <input type="text" id="messageInput" placeholder="Ketik pesan..." autofocus
                    class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition flex items-center gap-2">
                    Kirim
                </button>
            </form>
        </div>
    </div>

    <div class="col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-bold">Tool Tersedia</h3>
                <p class="text-xs text-gray-500">Terintegrasi dengan Agent Connector</p>
            </div>
            <div id="toolList" class="divide-y divide-gray-100">
                <div class="p-5 text-center text-sm text-gray-500">Memuat...</div>
            </div>
        </div>
    </div>
</div>

<script>
function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function addMessage(text, isUser = false) {
    const div = document.createElement('div');
    div.className = `flex mb-1 ${isUser ? 'justify-end' : ''}`;
    const safe = escapeHtml(text).replace(/\n/g, '<br>');
    div.innerHTML = `
        <div class="${isUser ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200'} rounded-xl px-4 py-2.5 text-sm shadow-sm" style="max-width: 80%; white-space: pre-wrap;">
            ${safe}
        </div>
    `;
    document.getElementById('chatMessages').appendChild(div);
    document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
}

async function sendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('messageInput');
    const msg = input.value.trim();
    if (!msg) return;

    addMessage(msg, true);
    input.value = '';

    const typing = document.createElement('div');
    typing.className = 'flex';
    typing.id = 'typing';
    typing.innerHTML = '<div class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-500 shadow-sm"><em>Mengetik...</em></div>';
    document.getElementById('chatMessages').appendChild(typing);
    document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;

    try {
        const res = await fetch('{{ route('agentconnector.chat') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ message: msg })
        });
        const data = await res.json();
        document.getElementById('typing')?.remove();
        addMessage(data.response || 'Maaf, tidak bisa memproses pesan.');
    } catch (e) {
        document.getElementById('typing')?.remove();
        addMessage('Gagal terhubung ke server.');
    }
}

function sendQuick(text) {
    document.getElementById('messageInput').value = text;
    document.getElementById('chatForm').dispatchEvent(new Event('submit'));
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
                        <strong class="text-sm text-gray-800">${t.name}</strong>
                    </div>
                    <div class="text-xs text-gray-500 mt-1 ml-4">${t.description}</div>
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
</script>
<style>.cursor-pointer { cursor: pointer; }</style>
@endsection
