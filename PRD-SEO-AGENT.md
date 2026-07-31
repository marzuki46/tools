# PRD: SEO Agent Telegram — Keyword Cluster & Auto Automation

## Ringkasan

Sistem otomasi SEO end-to-end. User cukup daftarkan **cluster keyword** (parent + child keyword),
sistem akan otomatis: riset LSI → buat konten → cek kualitas → ambil gambar → publish ke WordPress
→ internal link → ping search engine — semua otomatis tanpa campur tangan manual.

---

## Arsitektur Umum

```
User TG / Web UI
       │
       ▼
┌──────────────────────────────────────────────┐
│              AutoClusterAgent                 │
│  (Orkestrator utama, jalan tiap 30 menit)     │
├──────────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐  ┌────────────┐  │
│  │ Cluster  │  │ Content  │  │ WordPress  │  │
│  │ Service  │  │ Service  │  │ Service    │  │
│  └────┬─────┘  └────┬─────┘  └─────┬──────┘  │
│       │              │              │          │
│  ┌────▼─────┐  ┌────▼─────┐  ┌─────▼──────┐  │
│  │ Image    │  │ Quality  │  │ Ping       │  │
│  │ Service  │  │ Checker  │  │ Service    │  │
│  └──────────┘  └──────────┘  └────────────┘  │
└──────────────────────────────────────────────┘
       │
       ▼
┌──────────────────────────────────────────────┐
│         Module yang sudah ada:                │
│  KeywordResearch ─── ContentGenerator         │
└──────────────────────────────────────────────┘
```

---

## 1. Database Tables

### 1.1 `keyword_clusters`

| Kolom | Tipe | Fungsi |
|-------|------|--------|
| `id` | bigint PK | |
| `user_id` | bigint FK → `users` | Pemilik cluster |
| `name` | string(255) | Nama cluster (e.g. "Jasa Web") |
| `parent_keyword` | string(255) | Keyword utama cluster |
| `description` | text nullable | Deskripsi cluster |
| `status` | enum: `draft`,`active`,`paused`,`completed` | Status cluster |
| `schedule` | enum: `manual`,`daily`,`every_6h`,`every_12h` | Jadwal posting |
| `total_keywords` | int | Jumlah child keyword |
| `published_count` | int | Jumlah sudah terposting |
| `failed_count` | int | Jumlah gagal |
| `image_keyword` | string(255) nullable | Keyword untuk cari gambar (default: parent_keyword) |
| `image_source` | enum: `duckduckgo`,`bing`,`unsplash` | Sumber gambar |
| `image_enabled` | boolean default true | Aktif/nonaktif gambar |
| `image_per_article` | int default 3 | Maks gambar per artikel |
| `webp_quality` | int default 80 | Kualitas WebP (1-100) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 1.2 `cluster_keywords`

| Kolom | Tipe | Fungsi |
|-------|------|--------|
| `id` | bigint PK | |
| `cluster_id` | bigint FK → `keyword_clusters` | Induk cluster |
| `keyword` | string(255) | Child keyword |
| `status` | enum: `pending`,`researching`,`researched`,`generating`,`content_generated`,`publishing`,`published`,`failed` | Status progres |
| `keyword_research_id` | bigint FK → `keyword_researches` nullable | Hasil riset |
| `content_generation_id` | bigint FK → `content_generations` nullable | Konten yg dihasilkan |
| `post_url` | string nullable | URL artikel terposting |
| `published_at` | timestamp nullable | Waktu posting |
| `error_message` | text nullable | Error terakhir |
| `priority` | int default 0 | Urutan prioritas (kecil = duluan) |
| `retry_count` | int default 0 | Jumlah retry |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 1.3 `cluster_automation_logs`

| Kolom | Tipe | Fungsi |
|-------|------|--------|
| `id` | bigint PK | |
| `cluster_id` | bigint FK → `keyword_clusters` | |
| `keyword_id` | bigint FK → `cluster_keywords` nullable | |
| `action` | enum: `research`,`generate`,`quality_check`,`image`,`publish`,`ping`,`skip`,`error` | Tindakan |
| `status` | enum: `started`,`completed`,`failed` | |
| `message` | text | Log detail |
| `duration_ms` | int nullable | Lama proses (ms) |
| `started_at` | timestamp | |
| `completed_at` | timestamp nullable | |

### 1.4 `cluster_analytics`

| Kolom | Tipe | Fungsi |
|-------|------|--------|
| `id` | bigint PK | |
| `cluster_id` | bigint FK → `keyword_clusters` | |
| `date` | date | Tanggal |
| `keywords_processed` | int | Jumlah diproses hari ini |
| `keywords_published` | int | Jumlah terposting |
| `keywords_failed` | int | Jumlah gagal |
| `avg_duration_minutes` | float | Rata-rata waktu proses |
| `avg_quality_score` | float | Rata-rata skor kualitas |
| `success_rate` | float | Persentase sukses (0-100) |

### 1.5 `cluster_keyword_analytics`

| Kolom | Tipe | Fungsi |
|-------|------|--------|
| `id` | bigint PK | |
| `cluster_keyword_id` | bigint FK → `cluster_keywords` | |
| `post_url` | string nullable | URL artikel |
| `published_at` | timestamp nullable | |
| `posted_hour` | int nullable | Jam posting (0-23) |
| `quality_score` | float nullable | Skor readability |
| `word_count` | int nullable | Jumlah kata |
| `tokens_used` | int default 0 | Token AI yg dipakai |
| `image_count` | int default 0 | Jumlah gambar |

---

## 2. Services Layer

### 2.1 ClusterService

| Method | Input | Output | Fungsi |
|--------|-------|--------|--------|
| `createCluster(data)` | name, parent_keyword, keywords[] | Cluster | Buat cluster + child keywords |
| `addKeyword(clusterId, keyword)` | cluster_id, keyword | ClusterKeyword | Tambah child keyword |
| `removeKeyword(keywordId)` | cluster_keyword_id | bool | Hapus child keyword |
| `getNextPendingKeyword(clusterId)` | cluster_id | ClusterKeyword or null | Ambil keyword berikutnya (priority ASC) |
| `updateKeywordStatus(id, status, data)` | id, status, array | void | Update status + data tambahan |
| `getClusterProgress(clusterId)` | cluster_id | array{total, pending, done, failed} | Progress cluster |
| `activateCluster(clusterId)` | cluster_id | void | Set status=active |
| `pauseCluster(clusterId)` | cluster_id | void | Set status=paused |
| `getAutomationSummary()` | - | array | Ringkasan semua cluster |

### 2.2 WordPressService

| Method | Input | Output | Fungsi |
|--------|-------|--------|--------|
| `publishPost(title, content, meta)` | title, content, meta{slug, excerpt, categories, tags} | array{id, url} | Post artikel via REST API |
| `uploadMedia(file, filename)` | file, filename | array{id, url} | Upload gambar ke WP |
| `getExistingPosts(limit)` | int | Collection | Ambil artikel yg sudah ada |
| `getCategories()` | - | array | Daftar kategori WP |
| `getTags()` | - | array | Daftar tag WP |
| `testConnection()` | - | bool | Test koneksi REST API |

### 2.3 ImageService

| Method | Input | Output | Fungsi |
|--------|-------|--------|--------|
| `searchDuckDuckGo(keyword, count)` | keyword, count | array{url, width, height} | Cari gambar dari DuckDuckGo |
| `downloadRandom(keyword, count)` | keyword, count | array{tempPath, sourceUrl} | Download gambar random |
| `convertToWebP(sourcePath, quality)` | sourcePath, quality | string tempPath | Convert & compress ke WebP |
| `fetchAndUpload(keyword, wpService, count)` | keyword, wpService, count | array{wpId, wpUrl}[] | Download → WebP → Upload ke WP |
| `suggestImageKeywords(content)` | content text | string[] | Extract keyword dari konten buat cari gambar |

### 2.4 InternalLinkService

| Method | Input | Output | Fungsi |
|--------|-------|--------|--------|
| `findLinkOpportunities(content, existingPosts)` | content, posts[] | array{keyword, postUrl, anchorText}[] | Cari peluang link dari konten baru ke artikel existing |
| `injectLinks(content, opportunities, maxLinks)` | content, opp[], int | string | Sisipin link ke konten |

### 2.5 PingService

| Method | Input | Output | Fungsi |
|--------|-------|--------|--------|
| `pingGoogle(postUrl)` | url | bool | Ping Google via `blogsearch.google.com/ping` |
| `pingBing(postUrl)` | url | bool | Ping Bing via `bing.com/ping` |
| `pingIndexNow(postUrl, host)` | url, host | bool | Ping IndexNow API |
| `pingAll(postUrl)` | url | array | Ping semua search engine |

### 2.6 ContentAnalyzerService (Tools UI)

| Method | Input | Output | Fungsi |
|--------|-------|--------|--------|
| `analyze(content, keyword)` | content text, target keyword | AnalysisResult | Analisis SEO + struktur + readability |
| `analyzeSEO(content, keyword)` | content, keyword | array{density, title_length, desc_length, heading_structure, links} | Metrik SEO |
| `analyzeStructure(content)` | content | array{word_count, words_per_sentence, sentences_per_paragraph, total_paragraphs, heading_count} | Metrik struktur |
| `analyzeReadability(content)` | content | array{score, level, complex_word_ratio, avg_syllables, passive_voice} | Skor readability |
| `analyzeImages(html)` | html | array{total, with_alt, webp_count} | Analisis gambar |

**Output ContentAnalyzerService:**

```json
{
  "seo_score": 85,
  "structure_score": 72,
  "readability_score": 78,
  "image_score": 90,
  "total_score": 81,
  "details": {
    "keyword_density": 2.3,
    "meta_title_length": 55,
    "meta_description_length": 148,
    "heading_order": "H1→H2→H3",
    "total_words": 1245,
    "avg_words_per_sentence": 14.2,
    "avg_sentences_per_paragraph": 4.1,
    "total_paragraphs": 8,
    "total_headings": 12,
    "readability_score": 78,
    "complex_word_ratio": 12,
    "avg_syllables_per_word": 2.1,
    "passive_voice_percent": 25,
    "total_images": 2,
    "images_with_alt": 2,
    "images_webp": 2
  },
  "issues": [
    "Kalimat pasif 25% (ideal ≤20%)"
  ]
}
```

---

## 3. Automation Flow

### 3.1 Siklus Agent (jalan tiap 30 menit via cron)

```
Schedule:run → AutoClusterAgent::runCycle()
                       │
                       ▼
              ┌─────────────────┐
              │ Cek semua       │
              │ cluster active   │
              └────────┬────────┘
                       ▼
              ┌─────────────────┐
              │ Ambil 1 keyword  │
              │ pending (priority)│
              └────────┬────────┘
                       ▼
              ┌─────────────────┐
         ╔════╡ Cek jam posting  ╞════╗
         ║    │ 08:00-22:00?     │    ║
         ║    └────────┬────────┘    ║
         ║             ▼             ║
         ║    ┌─────────────────┐    ║
         ║    │ Riset keyword    │    ║
         ║    │ (KeywordResearch)│    ║
         ║    └────────┬────────┘    ║
         ║             ▼             ║
         ║    ┌─────────────────┐    ║
         ║    │ Generate konten  │    ║
         ║    │ (ContentGenerator)│   ║  Loop ke keyword
         ║    └────────┬────────┘    ║  berikutnya jika
         ║             ▼             ║  masih ada waktu
         ║    ┌─────────────────┐    ║  & kuota harian
         ║    │ Quality check    │    ║
         ║    │ ≥ 50?           │────║──→ failed → retry/log
         ║    └────────┬────────┘    ║
         ║             ▼             ║
         ║    ┌─────────────────┐    ║
         ║    │ Cari + upload    │    ║
         ║    │ gambar (WebP)    │    ║
         ║    └────────┬────────┘    ║
         ║             ▼             ║
         ║    ┌─────────────────┐    ║
         ║    │ Internal link    │    ║
         ║    └────────┬────────┘    ║
         ║             ▼             ║
         ║    ┌─────────────────┐    ║
         ║    │ Publish ke WP    │    ║
         ║    └────────┬────────┘    ║
         ║             ▼             ║
         ║    ┌─────────────────┐    ║
         ║    │ Ping search     │    ║
         ║    │ engine          │    ║
         ║    └────────┬────────┘    ║
         ║             ▼             ║
         ║    ┌─────────────────┐    ║
         ║    │ Log analytics    │    ║
         ║    └────────┬────────┘    ║
         ║             ▼             ║
         ║    ┌─────────────────┐    ║
         ╚════╡ Selesai 1 siklus ════╝
              └─────────────────┘
```

### 3.2 Langkah Detail

| # | Langkah | Service | Max Retry | Timeout | Log Action |
|---|---------|---------|-----------|---------|------------|
| 1 | Ambil keyword pending berikutnya | ClusterService | - | - | - |
| 2 | Buat keyword research | KeywordResearch module | 3x (1 menit jeda) | 120s | `research` |
| 3 | Simpan LSI + entities ke cluster_keyword | ClusterService | - | - | - |
| 4 | Generate konten 3 phase | ContentGenerator module | 3x (5 menit jeda) | 300s | `generate` |
| 5 | Cek kualitas (readability ≥ 50) | ContentAnalyzerService | 1x regenerate | 60s | `quality_check` |
| 6 | Cari & upload gambar | ImageService + WordPressService | 2x | 120s | `image` |
| 7 | Cari & sisip internal link | InternalLinkService | - | 60s | `link` |
| 8 | Publish ke WordPress | WordPressService | 3x (15 menit jeda) | 120s | `publish` |
| 9 | Ping search engine | PingService | 2x | 30s | `ping` |
| 10 | Update analytics | ClusterService | - | - | - |

### 3.3 Aturan Main (Business Rules)

| # | Aturan | Detail |
|---|--------|--------|
| 1 | **Kualitas minimal** | Readability ≥ 50. Jika di bawah, regenerate 1x. Jika masih di bawah → skip & failed |
| 2 | **Jeda antar posting** | Minimal 4 jam antar posting dalam 1 cluster |
| 3 | **Batas harian** | Maks 3 posting per hari per cluster (via settings) |
| 4 | **Jam posting** | Hanya posting dalam jendela 08:00-22:00 (configurable) |
| 5 | **Auto-pause** | Jika 3 keyword berturut-turut gagal → cluster auto-pause, notifikasi Telegram |
| 6 | **Retry backoff** | Riset gagal → 1 menit. Generate gagal → 5 menit. Publish gagal → 15 menit |
| 7 | **Skip duplikat** | Keyword yang URL-nya sudah pernah terposting → skip |
| 8 | **Siklus cron** | Agent berjalan setiap 30 menit via `schedule:run`. 1 keyword per siklus |
| 9 | **Prioritas** | Keyword dengan priority paling kecil dikerjakan duluan |
| 10 | **Cluster completed** | Semua keyword terposting → status auto-jadi `completed` |
| 11 | **Analisis jam aktif** | Sistem mencatat jam posting & sukses/gagal untuk rekomendasi jam terbaik |

---

## 4. Image Management

### 4.1 DuckDuckGo Image Search

```
ImageService::searchDuckDuckGo("jasa website", 10)
       │
       ▼
GET https://duckduckgo.com/i.js?q=jasa+website&limit=10
       │
       ▼
Parse JSON → ambil URL gambar (thumbnail/image)
       │
       ▼
Filter: ukuran > 200px, format jpg/png/webp
       │
       ▼
Random 3 gambar dari hasil
       │
       ▼
Download → GD/Imagick convert WebP (quality 80)
       │
       ▼
Upload ke WordPress via /wp/v2/media
```

### 4.2 Alur di Konten

```
Generate konten selesai
       │
       ▼
Ambil keyword gambar dari cluster (image_keyword)
       │
       ▼
Cari 3 gambar dari DuckDuckGo
       │
       ▼
Compress → WebP → upload ke WP
       │
       ▼
Parse konten, cari posisi <p> yang relevan
(tiap ~300 kata sisipin 1 gambar)
       │
       ▼
Sisipin <figure><img src='...' alt='keyword' loading='lazy'><figcaption>...</figcaption></figure>
```

### 4.3 Settings Image

| Setting Key | Default | Fungsi |
|-------------|---------|--------|
| `seo-agent.image.source` | `duckduckgo` | Sumber gambar |
| `seo-agent.image.default_keyword` | `indonesia` | Keyword default gambar |
| `seo-agent.image.auto_enable` | `true` | Otomatis sisipin gambar |
| `seo-agent.image.max_per_article` | `3` | Maks gambar per artikel |
| `seo-agent.image.webp_quality` | `80` | Kualitas WebP (1-100) |
| `seo-agent.image.min_width` | `400` | Lebar minimal gambar (px) |
| `seo-agent.image.min_height` | `300` | Tinggi minimal gambar (px) |

---

## 5. Content Analysis Tool

### 5.1 UI (https://tools.juki.eu.org/content-analyzer)

Tool khusus untuk analisa konten secara detail. Input: paste konten HTML/teks + target keyword.
Output: skor + rekomendasi perbaikan.

### 5.2 Metrik

| Kategori | Metrik | Ideal | Bobot Skor |
|----------|--------|-------|------------|
| **SEO** | Keyword density | 1-3% | 20 |
| | Meta title length | 50-60 karakter | 10 |
| | Meta description length | 150-160 karakter | 10 |
| | Heading structure | H1→H2→H3 berurutan | 15 |
| | Internal links | ≥2 per 1000 kata | 10 |
| | External links | ≥1 per artikel | 5 |
| **Struktur** | Total kata | ≥1000 | 15 |
| | Rata-rata kata/kalimat | 10-20 | 10 |
| | Rata-rata kalimat/paragraf | 3-5 | 10 |
| | Total paragraf | ≥5 | 5 |
| | Heading count | ≥1 H2 per 300 kata | 10 |
| **Readability** | Skor readability | ≥60 | 20 |
| | Kata sulit | ≤15% | 10 |
| | Rata-rata suku kata/kata | ≤3 | 5 |
| | Kalimat pasif | ≤20% | 10 |
| **Gambar** | Jumlah gambar | ≥1 per 500 kata | 10 |
| | Alt text | Semua gambar wajib | 10 |
| | Format WebP | Semua gambar .webp | 5 |

### 5.3 Output Format

```
📊 ANALISA KONTEN — [judul]

🟢 SEO: 85/100
  • Keyword density: 2.3% ✅
  • Meta title: 55 karakter ✅
  • Meta desc: 148 karakter ✅
  • Heading: H1→H2→H3 rapi ✅
  • Internal link: 3 link ✅

📐 STRUKTUR: 72/100
  • Total: 1.245 kata ✅
  • Rata-rata kata/kalimat: 14.2 ✅
  • Rata-rata kalimat/paragraf: 4.1 ✅
  • Heading: 4 H2, 8 H3 ✅

📖 READABILITY: 78/100
  • Skor: 78 ✅
  • Kata sulit: 12% ✅
  • Sukukata/kata: 2.1 ✅

🖼️ GAMBAR: 2 gambar, semua ada alt + WebP ✅

🔴 MASALAH DITEMUKAN:
  1. Kalimat pasif 25% (ideal ≤20%)
  2. Kurang 1 internal link

💡 SARAN:
  • Ubah 3 kalimat pasif jadi aktif
  • Tambah link ke artikel terkait di paragraf 3
```

---

## 6. UI Pages

| URL | Fungsi | Auth |
|-----|--------|------|
| `/keyword-clusters` | Daftar cluster + progress bar | User |
| `/keyword-clusters/create` | Buat cluster baru | User |
| `/keyword-clusters/{id}` | Detail cluster: list keyword + status | User |
| `/keyword-clusters/{id}/edit` | Edit cluster | User |
| `/keyword-clusters/{id}/activate` | Aktifkan/mulai otomasi | User |
| `/content-analyzer` | Analisa konten (paste + analisis) | User |

### 6.1 Tool Registration

Tool `keyword-cluster` + `content-analyzer` harus didaftarkan ke tabel `tools`:

| name | slug | package_name | is_active |
|------|------|-------------|-----------|
| Keyword Clusters | `keyword-clusters` | `KeywordCluster` | true |
| Content Analyzer | `content-analyzer` | `ContentAnalyzer` | true |

---

## 7. Telegram Commands (Tambahan)

| Perintah | Fungsi |
|----------|--------|
| `cluster list` | Daftar semua cluster & progresnya |
| `cluster status <id>` | Detail status keyword per cluster |
| `cluster start <id>` | Mulai proses cluster otomatis |
| `cluster stop <id>` | Hentikan proses cluster |
| `cluster add <id> <keyword>` | Tambah keyword ke cluster |
| `agent status` | Status automasi: cluster aktif, jadwal, dsb |
| `analisa <id>` | Analisa konten (content_generation_id) |

---

## 8. Settings (Admin → Settings)

### 8.1 WordPress

| Key | Default | Tipe | Fungsi |
|-----|---------|------|--------|
| `seo-agent.wp.url` | `https://air.my.id` | url | URL WordPress |
| `seo-agent.wp.username` | - | text | Username WP REST API |
| `seo-agent.wp.password` | - | password | Application Password WP |

### 8.2 Automation

| Key | Default | Tipe | Fungsi |
|-----|---------|------|--------|
| `seo-agent.auto.post_time_start` | `08:00` | text | Jam mulai boleh posting |
| `seo-agent.auto.post_time_end` | `22:00` | text | Jam terakhir posting |
| `seo-agent.auto.posts_per_day` | `3` | text | Maks posting per hari |
| `seo-agent.auto.active_cluster_id` | - | text | Cluster yg sedang aktif |
| `seo-agent.auto.min_readability` | `50` | text | Skor readability minimal |

### 8.3 Image

| Key | Default | Tipe | Fungsi |
|-----|---------|------|--------|
| `seo-agent.image.source` | `duckduckgo` | text | Sumber gambar |
| `seo-agent.image.default_keyword` | `indonesia` | text | Keyword default gambar |
| `seo-agent.image.max_per_article` | `3` | text | Maks gambar per artikel |
| `seo-agent.image.webp_quality` | `80` | text | Kualitas WebP |

---

## 9. Analisa & Report (Otomatis)

| Fitur | Sumber Data | Output |
|-------|-------------|--------|
| **Jam posting terbaik** | `cluster_keyword_analytics.posted_hour` + success rate per jam | Rekomendasi: "Posting jam 09.00 punya success rate tertinggi" |
| **Performa keyword** | Keyword dgn durasi proses tercepat + skor kualitas tertinggi | Top 5 termudah & tersulit |
| **Cluster health** | `cluster_analytics.success_rate` 7 hari | ✅ Sehat (>80%), ⚠️ Sedang (50-80%), 🔴 Kritis (<50%) |
| **Efisiensi biaya** | `cluster_keyword_analytics.tokens_used` | Rata-rata token per keyword, estimasi biaya |
| **Trend kualitas** | Rata-rata `quality_score` per minggu | Grafik naik/turun kualitas konten |

---

## 10. Environment / Config

```
# .env atau Settings DB
TELEGRAM_BOT_TOKEN=xxx

# WordPress
WP_URL=https://air.my.id
WP_USERNAME=admin
WP_APP_PASSWORD=xxxx

# Automation
SEO_AGENT_POST_TIME_START=08:00
SEO_AGENT_POST_TIME_END=22:00
SEO_AGENT_POSTS_PER_DAY=3
SEO_AGENT_MIN_READABILITY=50

# Image
SEO_AGENT_IMAGE_SOURCE=duckduckgo
SEO_AGENT_IMAGE_DEFAULT_KEYWORD=indonesia
SEO_AGENT_IMAGE_MAX_PER_ARTICLE=3
SEO_AGENT_IMAGE_WEBP_QUALITY=80
```

---

## 11. Server

| Item | Value |
|------|-------|
| Path | `/home/belalangturbo/public_html/tools.juki.eu.org/` |
| PHP | `ea-php84` |
| Queue Worker | `cd {path}; ea-php84 artisan queue:work --timeout=240 --tries=3` |
| Cron Agent | `* * * * * cd {path}; ea-php84 artisan schedule:run >> /dev/null 2>&1` |

---

## 12. Implementation Order

| Fase | Komponen | Estimasi |
|------|----------|----------|
| 1 | Migration + Models (5 tabel) | - |
| 2 | ClusterService (CRUD + progress) | - |
| 3 | UI Cluster (index, create, detail) | - |
| 4 | WordPressService (REST API) | - |
| 5 | AutoClusterAgent (orchestrator) | - |
| 6 | ImageService (DuckDuckGo + WebP) | - |
| 7 | InternalLinkService | - |
| 8 | PingService | - |
| 9 | ContentAnalyzer (tool + metrik) | - |
| 10 | Telegram commands tambahan | - |
| 11 | Settings + Admin | - |
| 12 | Testing A-Z | - |

---

## 13. Agent Connector — The Brain

### 13.1 Konsep

**Agent Connector** adalah LLM-powered orchestrator yang jadi "otak" sistem. Dia:
1. Menerima input user (Telegram/Web/API)
2. Muat context dari **RAG memory** (riwayat, preferensi, fakta user)
3. LLM milih tool mana yang dipanggil & urutannya
4. Eksekusi rantai tool
5. Simpan hasil + pembelajaran ke RAG memory
6. Balas user

```
User Input (TG/Web/API)
       │
       ▼
┌──────────────────────────────────────────────────┐
│              Agent Connector                      │
│                                                   │
│  ┌─────────────┐   ┌──────────────────────────┐  │
│  │ Intent      │   │      Tool Router          │  │
│  │ Analyzer    │──▶│  (LLM milih tool)         │  │
│  └──────┬──────┘   └─────┬────────────────────┘  │
│         │                │                        │
│  ┌──────▼──────┐   ┌────▼────────────────────┐  │
│  │ RAG Memory  │   │  Tool Execution Chain    │  │
│  │ (Vector DB) │   │  - KeywordResearch       │  │
│  │             │   │  - ContentGenerator      │  │
│  │ embeddings  │   │  - KeywordCluster        │  │
│  │ via 9Router │   │  - ContentAnalyzer       │  │
│  └─────────────┘   │  - WordPressPublisher    │  │
│                     │  - ImageFetcher          │  │
│                     └─────────────────────────┘  │
└──────────────────────────────────────────────────┘
```

### 13.2 3 Tools yang Didaftarkan

| Tool | Slug | Fungsi |
|------|------|--------|
| Keyword Clusters | `keyword-clusters` | Manajemen cluster keyword + automasi publish |
| Content Analyzer | `content-analyzer` | Analisa kualitas konten (SEO/structure/readability/images) |
| Agent Connector | `agent-connector` | **Router otomatis** — terima perintah natural, panggil tool yg tepat, belajar dari memory |

### 13.3 Database Tables (Agent Connector)

**13.3.1 `agent_memories`** — RAG vector store

| Kolom | Tipe | Fungsi |
|-------|------|--------|
| `id` | bigint PK | |
| `user_id` | bigint FK → `users` | Pemilik memory |
| `type` | string(30) | `conversation`, `preference`, `fact`, `learning`, `tool_result` |
| `key` | string(255) | Identifier unik (e.g. `user_preference.tone`, `cluster.3.last_keyword`) |
| `content` | text | Isi memory |
| `embedding` | vector(1536) nullable | Vector embedding untuk semantic search |
| `metadata` | json nullable | Data tambahan (tool, cluster_id, keyword, dll) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**13.3.2 `agent_sessions`** — Active session tracking

| Kolom | Tipe | Fungsi |
|-------|------|--------|
| `id` | bigint PK | |
| `user_id` | bigint FK → `users` | |
| `session_id` | string(100) | Unique session (TG chat_id atau web session) |
| `active_tool` | string(50) nullable | Tool yg sedang dipakai |
| `context` | json | Context percakapan terakhir |
| `intent` | string(50) nullable | Intent terakhir yg terdeteksi |
| `started_at` | timestamp | |
| `last_activity_at` | timestamp | |

**13.3.3 `agent_tool_registry`** — Daftar tool yg dikenal Agent

| Kolom | Tipe | Fungsi |
|-------|------|--------|
| `id` | bigint PK | |
| `name` | string(100) | Nama tool |
| `slug` | string(50) unique | Slug tool |
| `description` | text | Deskripsi buat LLM (apa fungsinya) |
| `capabilities` | json | Daftar kemampuan: `[{action, description, input_schema, output_schema}]` |
| `endpoint` | string nullable | URL/class endpoint |
| `order` | int default 0 | Urutan prioritas |
| `is_active` | boolean default true | |
| `created_at` | timestamp | |

### 13.4 Cara Kerja Agent Connector

#### 13.4.1 Flow: User kirim perintah natural

```
1. User: "buatin konten jasa website" (via TG)
2. AgentConnector terima
3. Intent Analyzer:
   a. Generate embedding dari text user via 9Router /v1/embeddings
   b. Search di agent_memories (cosine similarity, top-5)
   c. Dapet context: user punya cluster "Jasa Web", keyword terakhir "website murah"
4. Tool Router (LLM call):
   System prompt: "Kamu adalah agent router. Tool tersedia: ... Pilih tool yg tepat."
   User prompt: "buatin konten jasa website" + context memory
   LLM output: { "tool": "keyword-clusters", "action": "create_content", "params": { "keyword": "jasa website" } }
5. Eksekusi: AgentConnector panggil KeywordCluster::generateContent()
6. Setelah selesai, simpan ke agent_memories:
   - type: conversation, content: "User minta konten jasa website"
   - type: tool_result, content: "ContentGeneration ID 123 berhasil"
   - type: fact, content: "User suka tone informatif" (dideteksi dari riwayat)
7. Balas user via Telegram
```

#### 13.4.2 Intent Categories (dideteksi LLM)

| Intent | Contoh Input | Tool yg Dipanggil |
|--------|-------------|-------------------|
| `create_cluster` | "buat cluster jasa web dengan keyword website murah, website profesional" | KeywordCluster |
| `list_clusters` | "cluster saya apa aja" | KeywordCluster |
| `cluster_status` | "progress cluster 1 gimana" | KeywordCluster |
| `start_cluster` | "jalankan cluster 1" | KeywordCluster |
| `analyze_content` | "analisa konten ini" | ContentAnalyzer |
| `research_keyword` | "riset keyword jasa website" | KeywordResearch (existing) |
| `generate_content` | "buat konten tentang jasa website" | ContentGenerator (existing) |
| `check_trend` | "apa yg trending" | GoogleTrendsService (existing) |
| `publish` | "publish artikel 123" | WordPressPublisher |
| `help` | "bantuan" | - (langsung balas) |
| `general_chat` | "halo" / "siapa kamu" | - (obrol biasa, simpan ke memory) |

#### 13.4.3 RAG Memory Flow Detail

```
User input: "buat konten website murah"

Step 1: EMBED
POST /v1/embeddings { model: "text-embedding-3-small", input: "buat konten website murah" }
→ vector [0.001, 0.532, ...] (1536 dimensi)

Step 2: SEARCH
SELECT * FROM agent_memories 
WHERE user_id = 1
ORDER BY cosine_similarity(embedding, :vector) DESC
LIMIT 5

→ Results:
  - "User suka tone informatif" (score: 0.91)
  - "Cluster Jasa Web: 12 keyword, 3 published" (score: 0.85)
  - "Keyword terakhir: website profesional" (score: 0.82)
  - "User minta konten tanpa gambar" (score: 0.76)
  - "Preferensi: locale id, min readability 60" (score: 0.71)

Step 3: INJECT KE LLM
System prompt:
  "Kamu adalah agent connector. Tool tersedia:
   - keyword-clusters: manage keyword clusters
   - content-generator: generate artikel 3-phase
   - content-analyzer: analisa kualitas konten
   ...
   Context memory user:\n{hasil_search}"

Step 4: LLM DECIDE
→ Output: {"tool": "keyword-clusters", "action": "generate_content", "params": {"keyword": "website murah", "tone": "informatif"}}

Step 5: EXECUTE + SAVE
  - AgentConnector panggil tool
  - Simpan hasil: INSERT INTO agent_memories (type='tool_result', ...)
  - Simpan embedding hasil juga biar next search lebih relevan
```

### 13.5 AgentConnectorService — Method List

| Method | Input | Output | Fungsi |
|--------|-------|--------|--------|
| `processInput(userId, input, source)` | user_id, text, source(tg/web/api) | array{response, tool_called, actions[]} | Entry point utama |
| `analyzeIntent(input, memories)` | text, memory[] | array{intent, confidence, params} | Deteksi intent via LLM |
| `retrieveMemories(userId, input)` | user_id, text | Memory[] | Cari memory relevan via embedding |
| `saveMemory(userId, type, key, content, metadata)` | ... | Memory | Simpan memory baru |
| `saveEmbedding(memoryId, text)` | memory_id, text | void | Generate & simpan embedding |
| `routeToTool(intent, memories)` | intent, context | array{tool, action, params} | LLM milih tool |
| `executeTool(tool, action, params)` | ... | array{result, status} | Eksekusi tool |
| `getToolRegistry()` | - | Tool[] | Daftar semua tool yg dikenal |
| `buildSystemPrompt()` | - | string | System prompt buat LLM |

### 13.6 Tool-to-Tool Connections

```
Agent Connector tahu hubungan antar tool:

KeywordResearch ──→ ContentGenerator
     (riset LSI)       (butuh LSI buat generate)

KeywordCluster ──→ KeywordResearch
     (butuh riset)     (riset keyword child)

KeywordCluster ──→ ContentGenerator
     (butuh konten)    (generate artikel)

KeywordCluster ──→ WordPressPublisher
     (publish)         (post ke WP)

ContentAnalyzer ──→ ContentGenerator
     (cek kualitas)    (regenerate jika jelek)

Agent Connector bisa chain tool otomatis:
User: "buat konten jasa website dan publish"
→ LLM decide: panggil keyword-clusters
→ Cluster cari keyword pending
→ Panggil KeywordResearch (jika belum diriset)
→ Panggil ContentGenerator (generate)
→ Panggil WordPressPublisher (publish)
→ Balas user: "Artikel 'jasa website' berhasil dipublikasikan di https://..."
```

### 13.7 Database: `agent_tool_registry` — Initial Seed Data

| name | slug | description | capabilities |
|------|------|-------------|--------------|
| Keyword Clusters | `keyword-clusters` | Manage keyword clusters, track progress, auto-process keywords | `[{action: "list", description: "Lihat semua cluster"}, {action: "create", description: "Buat cluster baru"}, {action: "detail", description: "Lihat detail cluster"}, {action: "start", description: "Mulai otomasi cluster"}, {action: "stop", description: "Hentikan otomasi"}, {action: "add_keyword", description: "Tambah keyword ke cluster"}, {action: "status", description: "Progress cluster"}]` |
| Keyword Research | `keyword-research` | Riset LSI keywords dan entities dari target keyword | `[{action: "research", description: "Riset keyword", params: {keyword: string, locale: string}}]` |
| Content Generator | `content-generator` | Generate artikel 3-phase (draft, Q&A, final) | `[{action: "generate", description: "Generate artikel dari keyword", params: {keyword, tone, locale, lsi, entities}}]` |
| Content Analyzer | `content-analyzer` | Analisa kualitas konten: SEO, struktur, readability, gambar | `[{action: "analyze", description: "Analisa konten", params: {content, keyword}}]` |
| WordPress Publisher | `wordpress-publisher` | Publish artikel ke WordPress via REST API | `[{action: "publish", description: "Post artikel"}, {action: "upload_image", description: "Upload gambar"}]` |
| Image Fetcher | `image-fetcher` | Cari gambar dari DuckDuckGo, convert WebP, upload ke WP | `[{action: "fetch", description: "Cari & upload gambar", params: {keyword, count}}]` |
| Google Trends | `google-trends` | Cek tren keyword di Google | `[{action: "trend", description: "Cek tren keyword"}]` |

### 13.8 — Analogy Cara Kerja

```
┌─────────────────────────────────────────────────────────┐
│                    AGENT CONNECTOR                       │
│                                                          │
│  "Seperti manajer yang ngasih tugas ke tim-nya"          │
│                                                          │
│  User: "buat konten jasa website"                        │
│       │                                                   │
│       ▼                                                   │
│  ┌─────────────┐                                         │
│  │ Manajer     │───▶ Cek memory: user punya cluster?     │
│  │ (LLM)       │───▶ Cek preferensi: tone, locale        │
│  └──────┬──────┘───▶ Putuskan: panggil tool apa          │
│         │                                                 │
│         ├──▶ "KeywordResearch, riset keyword ini!"        │
│         │       │                                         │
│         │       ▼                                         │
│         │   LSI keywords + entities selesai               │
│         │                                                 │
│         ├──▶ "ContentGenerator, bikin artikel!"           │
│         │       │                                         │
│         │       ▼                                         │
│         │   Artikel selesai (3 phase)                     │
│         │                                                 │
│         ├──▶ "ImageFetcher, cari gambar!"                 │
│         │       │                                         │
│         │       ▼                                         │
│         │   Gambar terupload ke WP                        │
│         │                                                 │
│         └──▶ "WordPressPublisher, publish!"               │
│                 │                                         │
│                 ▼                                         │
│             Artikel live di web                           │
│                                                          │
│  Setelah semua: simpen ke memory — "user ini sukanya     │
│  tone informatif + gambar 3 buah, next time otomatis"    │
└──────────────────────────────────────────────────────────┘
```

### 13.9 Implementation Order (Update)

| Fase | Komponen | Detail |
|------|----------|--------|
| 1 | Migration + Models (5 tabel) | keyword_clusters, cluster_keywords, cluster_automation_logs, cluster_analytics, cluster_keyword_analytics |
| 2 | ClusterService (CRUD + progress) | + UI index, create, detail |
| 3 | WordPressService | REST API publish, upload media |
| 4 | ImageService | DuckDuckGo search, WebP conversion |
| 5 | InternalLinkService | Cari & sisip link ke artikel existing |
| 6 | PingService | Google, Bing, IndexNow |
| 7 | ContentAnalyzerService | Metrik SEO, struktur, readability, gambar |
| 8 | ContentAnalyzer UI | Tool page dengan paste + analisis |
| 9 | AutoClusterAgent | Cron-based orchestrator (jalan tiap 30 menit) |
| 10 | **Migration Agent Connector** (3 tabel) | **agent_memories, agent_sessions, agent_tool_registry** |
| 11 | **AgentConnectorService** | **Intent analyzer, RAG memory, tool router** |
| 12 | **Tool Registry Seed** | **Daftarkan 7 tool ke DB** |
| 13 | **Agent Connector UI** | **Tool page untuk ngobrol dengan agent** |
| 14 | **Integrasi Telegram** | **Agent Connector sebagai handler utama TG** |
| 15 | Settings + Admin | WP creds, auto config, image config |
| 16 | Testing A-Z | Full integration test
