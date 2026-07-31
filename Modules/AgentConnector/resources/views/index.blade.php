@extends('layouts.app')

@section('title', 'Agent Connector')

@section('content')
<div class="container-fluid py-3">
    <div class="row">
        <div class="col-md-8">
            <h4 class="mb-3">Agent Connector</h4>
            <div class="card">
                <div class="card-body" style="height: 500px; overflow-y: auto;" id="chatMessages">
                    <div class="text-center text-muted py-5">
                        <p class="mb-1">Selamat datang di Agent Connector!</p>
                        <small>Saya bisa bantu kamu mengelola cluster keyword, riset, generate konten, analisa, dan publish.</small>
                        <div class="mt-3">
                            <span class="badge bg-light text-dark me-1 cursor-pointer" onclick="sendQuick('buat cluster')">buat cluster</span>
                            <span class="badge bg-light text-dark me-1 cursor-pointer" onclick="sendQuick('cluster saya')">cluster saya</span>
                            <span class="badge bg-light text-dark me-1 cursor-pointer" onclick="sendQuick('analisa konten')">analisa konten</span>
                            <span class="badge bg-light text-dark cursor-pointer" onclick="sendQuick('bantuan')">bantuan</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <form id="chatForm" class="d-flex gap-2" onsubmit="return sendMessage(event)">
                        <input type="text" id="messageInput" class="form-control" placeholder="Ketik pesan..." autofocus>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">Tool Tersedia</div>
                <div class="card-body p-0" id="toolList">
                    <div class="text-center text-muted py-3">Memuat...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addMessage(text, isUser = false) {
    const div = document.createElement('div');
    div.className = `d-flex mb-2 ${isUser ? 'justify-content-end' : ''}`;
    div.innerHTML = `
        <div class="${isUser ? 'bg-primary text-white' : 'bg-light'} rounded p-2" style="max-width: 80%;">
            <small>${text.replace(/\n/g, '<br>')}</small>
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
    typing.className = 'd-flex mb-2';
    typing.id = 'typing';
    typing.innerHTML = '<div class="bg-light rounded p-2"><small><em>Mengetik...</em></small></div>';
    document.getElementById('chatMessages').appendChild(typing);

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
                `<div class="border-bottom p-2 small">
                    <strong>${t.name}</strong>
                    <div class="text-muted">${t.description}</div>
                </div>`
            ).join('');
        } else {
            list.innerHTML = '<div class="text-center text-muted py-3">Sinkronisasi tool dulu</div>';
        }
    } catch (e) {
        document.getElementById('toolList').innerHTML = '<div class="text-center text-muted py-3">Gagal memuat</div>';
    }
}
loadTools();
</script>
<style>.cursor-pointer { cursor: pointer; }</style>
@endsection
