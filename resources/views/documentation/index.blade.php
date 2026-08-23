@extends('layouts.app')

@section('title', 'Dokumentasi API')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Dokumentasi API</h1>
        <p class="text-gray-500 text-sm mt-1">Panduan lengkap penggunaan semua modul dan API</p>
    </div>

    {{-- Tabel Naming Convention --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">0. Naming Convention & Otomasi</h2>
        <p class="text-sm text-gray-600 mb-3">Semua endpoint API eksternal menggunakan prefix <code class="bg-gray-100 px-1 rounded">/api/v1/</code> dengan format:</p>
        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="text-left py-2 font-medium text-gray-600">Tool / Modul</th>
                    <th class="text-left py-2 font-medium text-gray-600">Slug (untuk API / tool/{slug})</th>
                    <th class="text-left py-2 font-medium text-gray-600">Endpoint Otomasi</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-medium">Keyword Research</td>
                    <td class="py-2 font-mono text-xs">keyword-research</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">POST /api/v1/tool/keyword-research/research</code><br><code class="bg-gray-100 px-1 rounded text-xs">POST /api/v1/tool/keyword-research/status</code></td>
                </tr>
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-medium">Content Generator</td>
                    <td class="py-2 font-mono text-xs">content-generator</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">POST /api/v1/tool/content-generator/generate</code><br><code class="bg-gray-100 px-1 rounded text-xs">POST /api/v1/tool/content-generator/status</code></td>
                </tr>
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-medium">Keyword Clusters</td>
                    <td class="py-2 font-mono text-xs">keyword-clusters</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">Web UI /keyword-clusters</code><br><code class="bg-gray-100 px-1 rounded text-xs">Command: seo-cluster:run</code></td>
                </tr>
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-medium">Content Analyzer</td>
                    <td class="py-2 font-mono text-xs">content-analyzer</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">POST /api/agent-connector/chat</code> (via Agent)<br><code class="bg-gray-100 px-1 rounded text-xs">Web UI /content-analyzer</code></td>
                </tr>
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-medium">Agent Connector</td>
                    <td class="py-2 font-mono text-xs">agent-connector</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">POST /api/agent-connector/chat</code><br><code class="bg-gray-100 px-1 rounded text-xs">GET /api/agent-connector/tools</code></td>
                </tr>
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-medium">Meta Title & Description</td>
                    <td class="py-2 font-mono text-xs">meta-generator</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">Otomatis (Phase 4) / POST .../generate-meta</code></td>
                </tr>
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-medium">Schema Markup</td>
                    <td class="py-2 font-mono text-xs">schema-markup</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">Otomatis (Phase 5) — Web UI only</code></td>
                </tr>
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-medium">Business Profiles</td>
                    <td class="py-2 font-mono text-xs">—</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">GET /api/v1/business-profiles</code></td>
                </tr>
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-medium">SEO Analyzer</td>
                    <td class="py-2 font-mono text-xs">seo-analyzer</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">Web UI only</code></td>
                </tr>
                <tr>
                    <td class="py-2 font-medium">Meta Ads Generator</td>
                    <td class="py-2 font-mono text-xs">meta-ads-generator</td>
                    <td class="py-2"><code class="bg-gray-100 px-1 rounded text-xs">POST /api/meta-ads/generate (Sanctum)</code></td>
                </tr>
            </tbody>
        </table>

        <h3 class="font-semibold text-sm mb-2">Pipeline Otomasi Lengkap (Shell Script)</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-2">#!/bin/bash
# ==========================================================================
# AUTO-CONTENT PIPELINE (FULL)
# 1. Riset keyword → 2. Ambil LSI/Entities → 3. Generate konten
#    → 4. Meta Title & Description (otomatis) → 5. Schema Article (otomatis)
# ==========================================================================
API_KEY="juki_xxx"
BASE="https://tools.juki.eu.org/api/v1"
KEYWORD="strategi digital marketing 2026"
LOCALE="id"
TONE="informative"
BP_ID=1                                     # ID Business Profile (opsional)

echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║           AUTO-CONTENT PIPELINE v2.0                         ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# ── Step 1: Riset Keyword ────────────────────────────────────────────
echo "━━━ [1/4] Riset Keyword: $KEYWORD ━━━"
RESEARCH=$(curl -s -X POST "$BASE/tool/keyword-research/research" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d "{\"keyword\": \"$KEYWORD\", \"locale\": \"$LOCALE\", \"lsi_count\": 12, \"entities_count\": 7}")
RID=$(echo "$RESEARCH" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
echo "  Research ID: $RID"
sleep 3

# ── Step 2: Tunggu Riset Selesai + Ambil Data ────────────────────────
echo "━━━ [2/4] Mengambil Hasil Riset ━━━"
while true; do
  RESULT=$(curl -s -X POST "$BASE/tool/keyword-research/status" \
    -H "X-API-Key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d "{\"id\": $RID}")
  STATE=$(echo "$RESULT" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
  echo "  Status riset: $STATE"
  [ "$STATE" = "completed" ] || [ "$STATE" = "pending" ] && break
  sleep 5
done

# Ekstrak LSI keywords & entities dari response
LSI=$(echo "$RESULT" | grep -o '"lsi_keywords":\[.*?\]' | sed 's/\\"/"/g')
ENTITIES=$(echo "$RESULT" | grep -o '"entities":\[.*?\]' | sed 's/\\"/"/g')
LSI_COUNT=$(echo "$LSI" | grep -o '"keyword"' | wc -l)
ENT_COUNT=$(echo "$ENTITIES" | grep -o '"name"' | wc -l)
echo "  LSI keywords: $LSI_COUNT | Entities: $ENT_COUNT"
echo ""

# ── Step 3: Generate Konten ──────────────────────────────────────────
echo "━━━ [3/4] Generate Konten ━━━"
CONTENT=$(curl -s -X POST "$BASE/tool/content-generator/generate" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d "{
    \"keyword\": \"$KEYWORD\",
    \"locale\": \"$LOCALE\",
    \"tone\": \"$TONE\",
    \"business_profile_id\": $BP_ID,
    \"lsi_keywords\": $LSI,
    \"entities\": $ENTITIES
  }")
CID=$(echo "$CONTENT" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
echo "  Content ID: $CID"

# ── Step 4: Polling Sampai Selesai ───────────────────────────────────
echo "━━━ [4/4] Menunggu Proses Selesai ━━━"
echo "  (Fase: 1=Draft, 2=Artikel, 3=Pertanyaan, 4=Final + Meta + Schema)"
echo ""
while true; do
  STATUS=$(curl -s -X POST "$BASE/tool/content-generator/status" \
    -H "X-API-Key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d "{\"id\": $CID}")
  STATE=$(echo "$STATUS" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
  PHASE=$(echo "$STATUS" | grep -o '"current_phase":[0-9]*' | cut -d: -f2)

  case $PHASE in
    0) LABEL="Draft" ;;
    1) LABEL="Fase 1: Artikel" ;;
    2) LABEL="Fase 2: Pertanyaan" ;;
    3) LABEL="Fase 3: Konten Final" ;;
    *) LABEL="Fase ${PHASE}: Proses..." ;;
  esac
  echo "  ⏳ Status: $STATE | $LABEL"

  [ "$STATE" = "completed" ] && break
  [ "$STATE" = "failed" ] && echo "  ❌ GAGAL!" && exit 1
  sleep 10
done

echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                        HASIL                                 ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# ── Ekstrak & Tampilkan Semua Output ─────────────────────────────────
MTITLE=$(echo "$STATUS" | grep -o '"meta_title":"[^"]*"' | cut -d'"' -f4)
MDESC=$(echo "$STATUS" | grep -o '"meta_description":"[^"]*"' | cut -d'"' -f4)
P1=$(echo "$STATUS" | grep -o '"phase_1_content":"[^"]*"' | cut -d'"' -f4)
P3=$(echo "$STATUS" | grep -o '"phase_3_content":"[^"]*"' | cut -d'"' -f4)
P3_LEN=$(echo "$P3" | wc -c)
P1_LEN=$(echo "$P1" | wc -c)

echo "━━━ META TITLE ━━━"
echo "  $MTITLE"
echo ""
echo "━━━ META DESCRIPTION ━━━"
echo "  $MDESC"
echo ""
echo "━━━ RINGKASAN ━━━"
echo "  Keyword       : $KEYWORD"
echo "  Content ID    : $CID"
echo "  Panjang Fase 1: ${P1_LEN} chars"
echo "  Panjang Final : ${P3_LEN} chars"
echo ""
echo "━━━ DETAIL ARTIKEL ━━━"
echo "  Buka di browser: https://tools.juki.eu.org/content-generator/$CID"
echo ""
echo "━━━ SCHEMA MARKUP ━━━"
echo "  Schema Article otomatis tergenerate (ID terhubung ke content #$CID)."
echo "  Cek di: https://tools.juki.eu.org/content-generator/$CID ?tab=schema"
echo "  Atau lihat semua schema: https://tools.juki.eu.org/schema-markup"
echo ""

# ── Simpan ke File (opsional) ────────────────────────────────────────
# echo "$P3" > "artikel-${CID}.md"
# echo "  📄 Artikel disimpan ke: artikel-${CID}.md"
echo "✅ PIPELINE SELESAI"</pre>
    </div>

    {{-- Cara Mendapatkan API Key --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">1. Mendapatkan API Key</h2>
        <p class="text-sm text-gray-600 mb-3">Setiap tool bisa diakses via API menggunakan API Key. Buat key baru di halaman <a href="{{ route('api-keys.index') }}" class="text-indigo-600 underline">API Keys</a>.</p>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200">API Key format: juki_{random}
Header: X-API-Key: juki_{random}
Alternatif: Authorization: Bearer juki_{random}</pre>
    </div>

    {{-- Queue Worker --}}
    <div class="bg-yellow-50 border border-yellow-200 p-6 rounded-xl">
        <h2 class="text-lg font-bold mb-3 text-yellow-800">2. Queue Worker</h2>
        <p class="text-sm text-yellow-700 mb-3">Proses generate konten berjalan di background via queue. Ada 2 cara:</p>

        <h3 class="font-semibold text-sm mb-1 text-yellow-800">Cara Manual</h3>
        <pre class="bg-yellow-100 p-3 rounded-lg text-sm font-mono border border-yellow-300 mb-4">php artisan queue:work --timeout=240</pre>

        <h3 class="font-semibold text-sm mb-1 text-yellow-800">Cara Otomatis (Direkomendasikan)</h3>
        <p class="text-sm text-yellow-700 mb-2">Sistem akan auto-start queue worker di background setiap kali ada konten baru yang dibuat atau fase di-retry. Cukup klik "Buat Konten" — worker menyala sendiri.</p>
        <p class="text-sm text-yellow-700 mb-3">Status worker bisa dilihat di dashboard <a href="{{ route('contentgenerator.index') }}" class="text-yellow-900 underline font-semibold">Content Generator</a>:
        <span class="inline-block w-3 h-3 rounded-full bg-green-500 align-middle mx-1"></span> Hijau = berjalan,
        <span class="inline-block w-3 h-3 rounded-full bg-red-500 align-middle mx-1"></span> Merah = macet,
        <span class="inline-block w-3 h-3 rounded-full bg-yellow-500 align-middle mx-1"></span> Kuning = idle.</p>

        <div class="bg-yellow-100 p-3 rounded-lg text-sm border border-yellow-300">
            <p class="text-yellow-800 font-medium">Auto-Retry:</p>
            <p class="text-yellow-700 mt-1">Jika job gagal, sistem otomatis coba ulang 3 kali dengan jeda 10s → 30s → 60s. Setelah 15 menit tanpa selesai, job dianggap gagal permanen. Tombol 🔄 Retry Semua di dashboard untuk requeue job yang gagal.</p>
        </div>
    </div>

    {{-- Content Generator --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">3. Content Generator</h2>
        <p class="text-sm text-gray-600 mb-3">Generate artikel dalam 4 fase: draft artikel, pertanyaan kritis, artikel final, meta SEO. Dilengkapi progress bar real-time dan auto-retry.</p>

        <h3 class="font-semibold text-sm mb-2">Fitur Baru</h3>
        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1 mb-4">
            <li><strong>Progress Bar:</strong> Visual 4 langkah (Antrian → Artikel → Pertanyaan → Konten Final) dengan animasi pulse</li>
            <li><strong>Auto-fill Keyword:</strong> Pilih riset keyword → keyword target terisi otomatis</li>
            <li><strong>Filter Riset:</strong> Riset yang sudah punya konten selesai tidak muncul di daftar</li>
            <li><strong>Profil Bisnis:</strong> Pilih profil → info bisnis disisipkan AI ke artikel</li>
            <li><strong>Meta SEO:</strong> Auto-generate title & description high-CTR (lihat section #4)</li>
            <li><strong>Rating & Feedback:</strong> Bintang 1-5 + jadikan referensi + catatan untuk AI</li>
            <li><strong>Dashboard Stats:</strong> Total rekues, berhasil, antrian, gagal, user aktif + lampu status worker</li>
        </ul>

        <h3 class="font-semibold text-sm mb-2">Endpoint API Eksternal: Generate</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">POST /api/v1/tool/content-generator/generate
X-API-Key: juki_{key}
Content-Type: application/json

{
    "keyword": "topik artikel",
    "locale": "id",
    "tone": "informative",
    "business_profile_id": 1,
    "lsi_keywords": [
        {"keyword": "kata kunci terkait 1"},
        {"keyword": "kata kunci terkait 2"}
    ],
    "entities": [
        {"name": "Nama Entity", "type": "Jenis"}
    ]
}</pre>

        <h3 class="font-semibold text-sm mb-2">Cek Status + Ambil Hasil</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">POST /api/v1/tool/content-generator/status
X-API-Key: juki_{key}
Content-Type: application/json

{"id": 1}

Response:
{
  "success": true,
  "data": {
    "id": 1,
    "target_keyword": "topik artikel",
    "status": "completed",
    "current_phase": 3,
    "phase_1_content": "...",
    "phase_2_questions": [...],
    "phase_3_content": "...",
    "meta_title": "Judul SEO Friendly [Panduan 2026]",
    "meta_description": "Baca panduan lengkap...",
    "created_at": "...",
    "updated_at": "..."
  }
}</pre>

        <h3 class="font-semibold text-sm mb-2">Parameter Lengkap</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="text-left py-2 font-medium text-gray-600">Parameter</th>
                    <th class="text-left py-2 font-medium text-gray-600">Wajib</th>
                    <th class="text-left py-2 font-medium text-gray-600">Deskripsi</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">keyword</td><td class="py-2 text-green-600">Ya</td><td class="py-2">Target keyword artikel</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">locale</td><td class="py-2 text-gray-400">Tidak</td><td class="py-2">id / en (default: id)</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">tone</td><td class="py-2 text-gray-400">Tidak</td><td class="py-2">informative, formal, casual, persuasive, storytelling, instructional</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">lsi_keywords</td><td class="py-2 text-gray-400">Tidak</td><td class="py-2">Array LSI keywords dari hasil riset</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">entities</td><td class="py-2 text-gray-400">Tidak</td><td class="py-2">Array entities dari hasil riset</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">business_profile_id</td><td class="py-2 text-gray-400">Tidak</td><td class="py-2">ID Profil Bisnis — info bisnis disisipkan ke artikel + dipakai schema</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">keyword_research_id</td><td class="py-2 text-gray-400">Tidak</td><td class="py-2">ID riset keyword (ambil LSI/entities otomatis)</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">link_sources</td><td class="py-2 text-gray-400">Tidak</td><td class="py-2">Array URL internal yang BOLEH ditautkan: <code>[{"title":"...","url":"...","keyword":"...","type":"post|home|category"}]</code> — AI memilih 3–5 paling relevan. Anchor Beranda = nama brand/situs, anchor Kategori = nama kategori (masing-masing wajib 1×)</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">target_words</td><td class="py-2 text-gray-400">Tidak</td><td class="py-2">Target jumlah kata artikel</td></tr>
                <tr><td class="py-2 font-mono text-xs">include_external_links</td><td class="py-2 text-gray-400">Tidak</td><td class="py-2">true / false — izinkan tautan keluar</td></tr>
            </tbody>
        </table>

        <h3 class="font-semibold text-sm mb-2 mt-6">Sinkronisasi Inventaris URL Situs</h3>
        <p class="text-sm text-gray-600 mb-3">Plugin WordPress mengirim seluruh URL terpublish + focus keyword SEO (Yoast/RankMath/AIOSEO) sebagai basis internal link & validasi URL silo. Bersifat full-sync: URL yang tidak ada di payload akan dihapus dari sistem. Field <code class="bg-gray-100 px-1 rounded">site_name</code> dipakai sebagai anchor Beranda pada semua artikel.</p>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">POST /api/v1/tool/content-generator/sync-inventory
{
  "site_name": "Nama Situs Anda",
  "items": [
    {"url": "https://situs.com/artikel/", "title": "Judul Artikel", "keyword": "focus keyword"}
  ]
}

Respons: {"success": true, "data": {"synced": 42, "removed": 3}}</pre>
    </div>

    {{-- Meta Title & Description --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">4. Meta Title & Description Generator</h2>
        <p class="text-sm text-gray-600 mb-3">Generate Meta Title (max 65 karakter) dan Meta Description (max 165 karakter) dengan fokus High CTR. Menggunakan power words, angka, bracket, value proposition, dan micro-CTA.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <p class="text-xs font-semibold text-green-700 uppercase tracking-wider">Cara Kerja</p>
                <ul class="text-sm text-green-700 mt-2 space-y-1 list-disc list-inside">
                    <li>Otomatis setelah Fase 3 selesai (jika tool aktif)</li>
                    <li>Baca keyword target + konten Fase 3 + LSI keywords</li>
                    <li>Bisa regenerate manual kapan saja</li>
                    <li>Lihat preview Google SERP di tab "Meta SEO"</li>
                </ul>
            </div>
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wider">Prompt AI</p>
                <ul class="text-sm text-indigo-700 mt-2 space-y-1 list-disc list-inside">
                    <li>Title: mengandung keyword, power words, bracket</li>
                    <li>Description: value proposition di awal, keyword 1-2x</li>
                    <li>Micro-CTA di akhir description</li>
                    <li>Output JSON: {"title": "...", "description": "..."}</li>
                </ul>
            </div>
        </div>

        <h3 class="font-semibold text-sm mb-2">Endpoint API</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">POST /api/v1/tool/content-generator/generate-meta
X-API-Key: juki_{key}
Content-Type: application/json

{"id": 1}

Response:
{
  "success": true,
  "data": {
    "id": 1,
    "meta_title": "7 Cara Digital Marketing [Panduan 2026]",
    "meta_description": "Tingkatkan penjualan dengan 7 strategi digital marketing terbaru. Cocok untuk UMKM dan pebisnis online. Pelajari selengkapnya!"
  }
}</pre>

        <p class="text-sm text-gray-600">Lihat preview & regenerate dari halaman detail Content Generator → tab <strong>"Meta SEO"</strong>.</p>
    </div>

    {{-- Business Profiles --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">5. Business Profiles (Profil Bisnis)</h2>
        <p class="text-sm text-gray-600 mb-3">Simpan informasi bisnis/website Anda agar AI bisa menyisipkannya secara natural ke dalam artikel. Data ini ditambahkan ke prompt Fase 1 sebagai konteks bisnis. Cocok untuk SEO lokal, konten marketing, dan branding.</p>

        <h3 class="font-semibold text-sm mb-2">Semua Field</h3>
        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="text-left py-2 font-medium text-gray-600">Field</th>
                    <th class="text-left py-2 font-medium text-gray-600">Keterangan</th>
                    <th class="text-left py-2 font-medium text-gray-600">Disisipkan ke Prompt</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">name *</td><td class="py-2">Nama profil (internal)</td><td class="py-2 text-gray-400">—</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">business_name</td><td class="py-2">Nama bisnis/perusahaan</td><td class="py-2 text-green-600">✅</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">website_url</td><td class="py-2">URL website</td><td class="py-2 text-green-600">✅</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">description</td><td class="py-2">Deskripsi bisnis</td><td class="py-2 text-green-600">✅</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">products_services</td><td class="py-2">Produk/jasa yang dijual</td><td class="py-2 text-green-600">✅</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">target_audience</td><td class="py-2">Target pasar</td><td class="py-2 text-green-600">✅</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">usp</td><td class="py-2">Keunggulan (Unique Selling Points)</td><td class="py-2 text-green-600">✅</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">contact_email</td><td class="py-2">Email kontak</td><td class="py-2 text-green-600">✅</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">contact_phone</td><td class="py-2">Telepon</td><td class="py-2 text-green-600">✅</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">address</td><td class="py-2">Alamat</td><td class="py-2 text-green-600">✅</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">business_hours</td><td class="py-2">Jam operasional</td><td class="py-2 text-green-600">✅</td></tr>
                <tr><td class="py-2 font-mono text-xs">social_media</td><td class="py-2">JSON object (instagram, facebook, twitter, youtube, tiktok, linkedin)</td><td class="py-2 text-green-600">✅</td></tr>
            </tbody>
        </table>

        <h3 class="font-semibold text-sm mb-2">API Endpoint</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">GET /api/v1/business-profiles
Headers: X-API-Key: juki_{key}

Response:
{
  "data": [
    {
      "id": 1,
      "name": "Website Utama",
      "business_name": "PT. Contoh Sejahtera",
      "website_url": "https://contoh.com",
      "description": "Perusahaan yang bergerak di bidang...",
      "products_services": "Jasa SEO, Web Development",
      "target_audience": "UMKM dan pebisnis online",
      "usp": "Sudah dipercaya 500+ klien"
    }
  ]
}</pre>

        <h3 class="font-semibold text-sm mb-2">Menggunakan di Content Generator</h3>
        <p class="text-sm text-gray-600 mb-3">Via Web: Pilih dari dropdown "Profil Bisnis" di form create. Via API: tambahkan field <code class="bg-gray-100 px-1 rounded">business_profile_id</code>.</p>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">curl -X POST https://tools.juki.eu.org/api/v1/tool/content-generator/generate \
  -H "X-API-Key: juki_{key}" \
  -H "Content-Type: application/json" \
  -d '{
    "keyword": "jasa seo murah",
    "locale": "id",
    "tone": "informative",
    "business_profile_id": 1
  }'</pre>

        <p class="text-sm text-gray-600">Atur profil di menu <a href="{{ route('business-profiles.index') }}" class="text-indigo-600 underline">🏢 Profil Bisnis</a>.</p>
    </div>

    {{-- Keyword Research --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">6. Keyword Research</h2>
        <p class="text-sm text-gray-600 mb-3">Riset keyword dengan LSI keywords dan entities secara otomatis via AI.</p>

        <h3 class="font-semibold text-sm mb-2">Endpoint API Eksternal</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">POST /api/v1/keyword-research/research
X-API-Key: juki_{key}
Content-Type: application/json

{
    "keyword": "topik riset",
    "locale": "id",
    "lsi_count": 15,
    "entities_count": 10,
    "webhook_url": "https://webhook.example.com/callback",
    "webhook_secret": "secret123"
}</pre>

        <h3 class="font-semibold text-sm mb-2">Cek Status Riset</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">GET /api/v1/keyword-research/research/{id}
X-API-Key: juki_{key}</pre>
    </div>

    {{-- Keyword Clusters --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">7. Keyword Clusters (Otomasi Konten)</h2>
        <p class="text-sm text-gray-600 mb-3">Kelompokkan keyword ke dalam cluster yang diolah otomatis: riset keyword → generate konten → ambil gambar → publish ke WordPress, sesuai jadwal. Alur ini dijalankan oleh <code class="bg-gray-100 px-1 rounded">AutoClusterAgent</code>.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <p class="text-xs font-semibold text-green-700 uppercase tracking-wider">Alur Otomasi per Keyword</p>
                <ol class="text-sm text-green-700 mt-2 space-y-1 list-decimal list-inside">
                    <li>Riset keyword (LSI + entities)</li>
                    <li>Generate konten 3-phase + internal link SILO otomatis</li>
                    <li>Analisa kualitas konten (analisa)</li>
                    <li>Ambil & upload gambar ke WordPress</li>
                    <li>Publish artikel ke WordPress</li>
                    <li>Ping Google/Bing/IndexNow</li>
                </ol>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Status Keyword</p>
                <ul class="text-sm text-blue-700 mt-2 space-y-1 list-disc list-inside">
                    <li><code class="bg-white px-1 rounded">pending</code> — menunggu diproses</li>
                    <li><code class="bg-white px-1 rounded">processing</code> — sedang diproses</li>
                    <li><code class="bg-white px-1 rounded">completed</code> — selesai & published</li>
                    <li><code class="bg-white px-1 rounded">failed</code> — gagal (max 3× retry)</li>
                </ul>
            </div>
        </div>

        <h3 class="font-semibold text-sm mb-2">API Endpoint</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">POST /api/v1/tool/keyword-clusters/list      # daftar cluster situs ini
POST /api/v1/tool/keyword-clusters/create    # buat struktur SILO dari topik (async)
POST /api/v1/tool/keyword-clusters/show      # detail cluster + status tiap keyword
POST /api/v1/tool/keyword-clusters/activate  # aktifkan otomasi
POST /api/v1/tool/keyword-clusters/pause     # jeda otomasi

Body create:
{
  "topic": "wisata di solo",            // wajib — topik utama silo
  "parent_count": 4,                    // 1–10 (default 4)
  "child_count": 4,                     // 1–15 per parent (default 4)
  "url_template": "https://situs.com/{slug}/",  // prediksi URL internal; string kosong bila permalink Plain
  "publish_start": "2026-08-24",        // opsional — jadwal terbit (Y-m-d)
  "publish_end": "2026-08-30",          // opsional — maksimal 2 tahun
  "tz_offset": 7                        // zona waktu situs (WIB = 7)
}</pre>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
            <p class="text-xs font-semibold text-amber-700 uppercase tracking-wider mb-1">Respons create = HTTP 202 (Async)</p>
            <p class="text-sm text-amber-700">Struktur dibuat oleh job antrian (<code class="bg-white px-1 rounded">ProcessClusterStructureJob</code>) karena generasi AI bisa lebih dari 30 detik — aman dari batas <code class="bg-white px-1 rounded">max_execution_time</code> hosting. Cluster muncul otomatis beberapa menit setelah request: <code class="bg-white px-1 rounded">{"success":true,"message":"Struktur SILO diantrikan..."}</code></p>
        </div>

        <h3 class="font-semibold text-sm mb-2">Hierarki & Internal Linking</h3>
        <p class="text-sm text-gray-600 mb-3">Satu topik dipecah menjadi beberapa <strong>parent keyword</strong>; tiap parent mendapat child dan ditutup <strong>artikel pillar</strong> yang menautkan semua child. Seluruh artikel dalam satu silo saling tertaut:</p>
        <ul class="text-sm text-gray-600 mb-3 list-disc list-inside space-y-1">
            <li><strong>Child →</strong> artikel pillar + sesama child dalam satu parent</li>
            <li><strong>Semua artikel →</strong> Beranda (anchor = nama brand/situs) dan halaman Kategori (anchor = nama kategori), masing-masing tepat 1×</li>
            <li>Hanya URL dari daftar sumber resmi yang boleh ditautkan — AI dilarang mengarang URL internal</li>
        </ul>
        <p class="text-sm text-gray-600">Flag konfigurasi (.env): <code class="bg-gray-100 px-1 rounded">SEO_CLUSTER_LINK_HOME=true</code>, <code class="bg-gray-100 px-1 rounded">SEO_CLUSTER_LINK_CATEGORY=true</code>. Nama brand diambil dari <code class="bg-gray-100 px-1 rounded">site_name</code> hasil sinkronisasi inventaris (section #3).</p>

        <h3 class="font-semibold text-sm mb-2 mt-4">Web UI</h3>
        <p class="text-sm text-gray-600 mb-3">Buka <a href="{{ route('seocluster.index') }}" class="text-indigo-600 underline">Keyword Clusters</a> (URL <code class="bg-gray-100 px-1 rounded">/keyword-clusters</code>). Dari sini Anda bisa: buat cluster (nama + parent keyword + daftar keyword), aktifkan/pause otomasi, tambah/hapus keyword, dan lihat progress tiap keyword.</p>

        <h3 class="font-semibold text-sm mb-2">Cron / Scheduler</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3"># Jalankan manual satu siklus:
ea-php84 artisan seo-cluster:run

# Otomatis: sudah terdaftar di scheduler (bootstrap/app.php) tiap 30 menit
ea-php84 artisan schedule:run</pre>

        <h3 class="font-semibold text-sm mb-2">Koneksi WordPress</h3>
        <p class="text-sm text-gray-600 mb-3">Otomasi publish memerlukan konfigurasi WP di halaman <a href="{{ route('admin.settings') }}" class="text-indigo-600 underline">AI Settings</a> (key <code class="bg-gray-100 px-1 rounded">seo-agent.wp.url</code>, <code class="bg-gray-100 px-1 rounded">username</code>, <code class="bg-gray-100 px-1 rounded">password</code> / App Password). Gambar dicari dari Bing/DuckDuckGo dan di-convert ke WebP.</p>
    </div>

    {{-- Content Analyzer --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">8. Content Analyzer</h2>
        <p class="text-sm text-gray-600 mb-3">Analisa kualitas konten artikel: skor SEO, struktur heading, readability, kata, paragraf, gambar, dan link — dengan saran perbaikan.</p>

        <h3 class="font-semibold text-sm mb-2">Web UI</h3>
        <p class="text-sm text-gray-600 mb-3">Buka <a href="{{ route('agentconnector.analyzer') }}" class="text-indigo-600 underline">Content Analyzer</a> (URL <code class="bg-gray-100 px-1 rounded">/content-analyzer</code>). Tempel konten HTML lalu klik Analisa.</p>

        <h3 class="font-semibold text-sm mb-2">Output Analisa</h3>
        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="text-left py-2 font-medium text-gray-600">Metrik</th>
                    <th class="text-left py-2 font-medium text-gray-600">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs font-medium">seo_score</td><td class="py-2">Skor 0-100 (keyword di title/meta/heading, kepadatan, struktur)</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs font-medium">readability</td><td class="py-2">Skor keterbacaan 0-100</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs font-medium">word_count</td><td class="py-2">Jumlah kata</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs font-medium">headings / paragraphs / images / links</td><td class="py-2">Hitung struktur HTML</td></tr>
                <tr><td class="py-2 font-mono text-xs font-medium">suggestions</td><td class="py-2">Daftar saran perbaikan</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Agent Connector --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">9. Agent Connector (Chat AI)</h2>
        <p class="text-sm text-gray-600 mb-3">Asisten AI satu pintu untuk semua tool: memahami perintah bahasa alami, memilih tool yang tepat, dan mengeksekusinya. Dilengkapi memori (RAG via embedding 9Router) agar konteks antar percakapan tetap terjaga.</p>

        <h3 class="font-semibold text-sm mb-2">Web UI Chat</h3>
        <p class="text-sm text-gray-600 mb-3">Buka <a href="{{ route('agentconnector.index') }}" class="text-indigo-600 underline">Agent Connector</a> (URL <code class="bg-gray-100 px-1 rounded">/agent-connector</code>). Contoh perintah:</p>
        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1 mb-4">
            <li><code class="bg-gray-100 px-1 rounded">buat cluster keyword seo lokal</code> → membuat cluster</li>
            <li><code class="bg-gray-100 px-1 rounded">cluster saya</code> → daftar cluster</li>
            <li><code class="bg-gray-100 px-1 rounded">riset keyword "seo lokal"</code> → keyword research</li>
            <li><code class="bg-gray-100 px-1 rounded">generate konten tentang jasa seo</code> → content generator</li>
            <li><code class="bg-gray-100 px-1 rounded">analisa konten ...</code> → content analyzer</li>
            <li><code class="bg-gray-100 px-1 rounded">bantuan</code> → daftar tool tersedia</li>
        </ul>

        <h3 class="font-semibold text-sm mb-2">API Chat</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200 mb-3">POST /api/agent-connector/chat
Authorization: Bearer {sanctum_token}
Content-Type: application/json

{ "message": "riset keyword seo lokal", "session_id": "telegram-123" }

Response:
{
  "response": "Riset keyword 'seo lokal' selesai. Ditemukan 12 LSI keywords dan 7 entities.",
  "intent": "research_keyword",
  "tool_called": "keyword-research",
  "actions": [ { "tool": "keyword-research", "status": "ok", ... } ]
}</pre>

        <h3 class="font-semibold text-sm mb-2">Tool Registry & Memori</h3>
        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="text-left py-2 font-medium text-gray-600">Endpoint</th>
                    <th class="text-left py-2 font-medium text-gray-600">Fungsi</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">GET /api/agent-connector/tools</td><td class="py-2">Daftar tool yang dikenali agent</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">POST /api/agent-connector/tools/sync</td><td class="py-2">Sinkronisasi registri tool ke database (panggil sekali setelah deploy/migrate)</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs">GET /api/agent-connector/memories</td><td class="py-2">Lihat memori percakapan user</td></tr>
            </tbody>
        </table>

        <div class="bg-green-50 border border-green-200 p-4 rounded-lg">
            <p class="text-sm text-green-800 font-medium">Auto-sync otomatis:</p>
            <p class="text-sm text-green-700 mt-1">Registri tool langsung terisi otomatis saat pertama kali agent digunakan (tabel <code class="bg-green-100 px-1 rounded">agent_tool_registries</code>). Manual sync via <code class="bg-green-100 px-1 rounded">POST /api/agent-connector/tools/sync</code> juga tersedia jika perlu refresh.</p>
        </div>
    </div>

    {{-- Sistem Memori & Feedback --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">10. Sistem Memori & Feedback</h2>
        <p class="text-sm text-gray-600 mb-3">Setiap artikel yang berhasil digenerate disimpan sebagai memori. Sistem belajar dari konten terbaik untuk generasi berikutnya.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-sm mb-2">Cara Kerja Memori</h3>
                <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                    <li>Setiap Phase 3 selesai → simpan keyword + ringkasan + embedding</li>
                    <li>Saat konten baru → cari 3 memori terdekat (cosine similarity / keyword)</li>
                    <li>Memori disisipkan ke prompt Fase 1 sebagai referensi gaya</li>
                    <li>Embedding: 9Router text-embedding-3-small, fallback keyword overlap</li>
                </ul>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg">
                <h3 class="font-semibold text-sm mb-2">Feedback & Rating</h3>
                <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                    <li><strong>Rating ⭐ 1-5:</strong> Nilai kualitas artikel</li>
                    <li><strong>Jadikan Referensi:</strong> Tandai sebagai konten terbaik</li>
                    <li><strong>Catatan:</strong> Feedback spesifik untuk AI</li>
                    <li>Memori referensi mendapat <strong>+0.4 boost</strong> skor</li>
                    <li>Rating tinggi → <strong>+0.1 per poin > 3</strong></li>
                </ul>
            </div>
        </div>

        <h3 class="font-semibold text-sm mb-2">Tabel Database generation_memories</h3>
        <pre class="bg-gray-50 p-3 rounded-lg text-sm font-mono border border-gray-200">generation_memories
  - id, user_id, content_generation_id
  - keyword, locale, tone
  - lsi_keywords, entities (JSON)
  - summary (500 char), embedding (vector text)
  - quality_score (1-5), is_reference (boolean)
  - feedback (text)
  - created_at, updated_at</pre>
    </div>

    {{-- Schema Markup Generator --}}
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-lg font-bold mb-4">11. Schema Markup Generator</h2>
        <p class="text-sm text-gray-600 mb-3">Buat JSON-LD schema.org untuk 10 tipe konten: Article, FAQPage, Product, LocalBusiness, BreadcrumbList, Review, Recipe, VideoObject, HowTo, Event. Bisa auto-fill dari konten Content Generator yang sudah selesai, dilengkapi AI enhancement.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Fitur</p>
                <ul class="text-sm text-blue-700 mt-2 space-y-1 list-disc list-inside">
                    <li>10 tipe schema lengkap dengan field spesifik</li>
                    <li>Auto-fill data dari konten Content Generator yang completed</li>
                    <li>AI enhancement — isi otomatis data yang kurang</li>
                    <li>Preview JSON-LD + copy script tag siap pakai</li>
                    <li>Regenerate kapan saja (manual ↔ AI)</li>
                    <li>Tautan validasi: Google Rich Results Test & Schema.org Validator</li>
                </ul>
            </div>
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <p class="text-xs font-semibold text-purple-700 uppercase tracking-wider">Tipe Schema</p>
                <ul class="text-sm text-purple-700 mt-2 space-y-1 list-disc list-inside columns-2">
                    <li>Article</li>
                    <li>FAQPage</li>
                    <li>Product</li>
                    <li>LocalBusiness</li>
                    <li>BreadcrumbList</li>
                    <li>Review</li>
                    <li>Recipe</li>
                    <li>VideoObject</li>
                    <li>HowTo</li>
                    <li>Event</li>
                </ul>
            </div>
        </div>

        <h3 class="font-semibold text-sm mb-2">Auto-fill dari Content Generator</h3>
        <p class="text-sm text-gray-600 mb-3">Pilih konten yang sudah completed → data terisi otomatis:</p>
        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="text-left py-2 font-medium text-gray-600">Tipe Schema</th>
                    <th class="text-left py-2 font-medium text-gray-600">Auto-fill dari Konten</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs font-medium">Article</td><td class="py-2">headline → meta_title, description → meta_description, articleBody → phase_3_content, datePublished → created_at, author → user.name</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs font-medium">FAQPage</td><td class="py-2">FAQ items → phase_2_questions (question + answer)</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs font-medium">Product</td><td class="py-2">name → meta_title, description → meta_description/keyword, brand → dari Business Profile</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs font-medium">LocalBusiness</td><td class="py-2">Semua field dari Business Profile terkait (nama, alamat, telepon, jam operasional, sosial media)</td></tr>
                <tr class="border-b border-gray-100"><td class="py-2 font-mono text-xs font-medium">BreadcrumbList</td><td class="py-2">Items → Home + judul konten</td></tr>
                <tr><td class="py-2 font-mono text-xs font-medium">HowTo</td><td class="py-2">name → meta_title, description → meta_description, body → phase_3_content</td></tr>
            </tbody>
        </table>

        <h3 class="font-semibold text-sm mb-2">AI Enhancement</h3>
        <p class="text-sm text-gray-600 mb-3">Centang "Gunakan AI" saat create → AI akan melengkapi field yang kosong dan mengoptimalkan struktur schema. Data yang sudah diisi manual tetap dipertahankan.</p>

        <h3 class="font-semibold text-sm mb-2">Cara Penggunaan</h3>
        <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1 mb-4">
            <li>Buka <a href="{{ route('schema-markup.create') }}" class="text-indigo-600 underline">Buat Schema Markup</a></li>
            <li>Pilih tipe schema</li>
            <li>(Opsional) Pilih konten Content Generator yang completed → data terisi otomatis</li>
            <li>Isi/ubah field manual sesuai kebutuhan</li>
            <li>(Opsional) Centang "Gunakan AI" untuk optimasi AI</li>
            <li>Klik Generate → lihat preview JSON-LD + script tag</li>
            <li>Copy script tag → paste di &lt;head&gt; halaman website</li>
        </ol>

        <h3 class="font-semibold text-sm mb-2">Validasi</h3>
        <p class="text-sm text-gray-600 mb-3">Dari halaman detail, klik tombol validasi untuk mengecek ke Google Rich Results Test atau Schema.org Validator.</p>
    </div>

    {{-- Tips --}}
    <div class="bg-indigo-50 border border-indigo-200 p-6 rounded-xl">
        <h2 class="text-lg font-bold mb-3 text-indigo-800">12. Tips Penggunaan</h2>
        <ul class="space-y-2 text-sm text-indigo-700">
            <li><strong>Pipeline otomatis (lengkap):</strong> Keyword Research → Content Generator → Meta SEO → Schema Markup — semua dalam 1 script. Mulai dari riset keyword hingga schema Article siap pakai. Lihat contoh shell script di section #0.</li>
            <li><strong>Queue worker:</strong> Tidak perlu manual — auto-start saat ada konten baru. Cek status lampu di dashboard.</li>
            <li><strong>Kualitas konten:</strong> Gunakan riset keyword dulu, pilih profil bisnis, beri rating untuk feedback ke AI.</li>
            <li><strong>Profil Bisnis:</strong> Buat beberapa profil (misal: "Website A" dan "Toko B") — pilih sesuai kebutuhan konten.</li>
            <li><strong>Meta SEO:</strong> Generate otomatis setelah konten selesai. Regenerate jika perlu. Copy title/description langsung dari tab Meta.</li>
            <li><strong>Retry otomatis:</strong> Job gagal di-retry 3 kali dengan jeda meningkat. Tombol 🔄 Retry Semua di dashboard untuk failed jobs.</li>
            <li><strong>Schema Markup:</strong> Auto-fill dari konten Content Generator + AI enhancement untuk hasil optimal. Jangan lupa validasi di Google Rich Results Test.</li>
            <li><strong>SEO Analyzer:</strong> Gratis — cek on-page SEO URL manapun tanpa biaya. Ideal untuk audit cepat sebelum publikasi.</li>
            <li><strong>Webhook:</strong> Keyword Research bisa kirim hasil ke URL kamu setelah selesai (parameter webhook_url).</li>
        </ul>
    </div>
</div>
@endsection
