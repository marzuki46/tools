# PRD: SEO Agent WhatsApp (Fonnte)

## Ringkasan
Agent AI yang terhubung dengan **Fonnte** (WhatsApp gateway) untuk melakukan riset keyword,
analisis tren, pembuatan konten SEO, pengecekan kualitas, dan publish artikel ke WordPress —
semua melalui chat WhatsApp.

## Arsitektur

```
User WA → Fonnte → Webhook → SeoAgentController → CommandParser → SeoAgentOrchestrator
                                                                  ├── KeywordResearchService
                                                                  ├── ContentGeneratorService
                                                                  ├── GoogleTrendsService
                                                                  ├── ContentQualityChecker
                                                                  └── FonnteService (reply)
```

## Komponen

### 1. FonnteService (`app/Services/FonnteService.php`)
Gateway WhatsApp via Fonnte API.

**Send message:** `POST https://api.fonnte.com/send`
- Headers: `Authorization: {token}`
- Body: `target=phone&message=text&countryCode=62`

**Webhook incoming:** POST ke `{base_url}/api/seo-agent/webhook`
- Body: `{"sender": "0812345xxx", "message": "riset keyword...", "name": "User"}`

### 2. CommandParser (`app/Services/SeoAgent/CommandParser.php`)
Parse natural language chat ke command terstruktur.

| Perintah WA | Command |
|---|---|
| `trend <keyword>` | `TREND` |
| `trending <keyword>` | `TREND` |
| `riset <keyword>` | `RESEARCH` |
| `research <keyword>` | `RESEARCH` |
| `buat konten <keyword>` | `GENERATE_CONTENT` |
| `konten <keyword>` | `GENERATE_CONTENT` |
| `cek <keyword>` | `CHECK_KEYWORD` |
| `status <id>` | `STATUS` |
| `panjang <id>` | `CONTENT_LENGTH` |
| `readability <id>` | `READABILITY` |
| `publish <id>` | `PUBLISH` |
| `bantuan` / `help` | `HELP` |

### 3. SeoAgentOrchestrator (`app/Services/SeoAgent/SeoAgentOrchestrator.php`)
Orkestrasi utama. Menerima parsed command, jalankan action, kirim reply ke WA.

### 4. GoogleTrendsService (`app/Services/SeoAgent/GoogleTrendsService.php`)
Cek tren keyword. Untuk MVP: analisis via AI (simulasi tren).
Kedepan: integrasi real Google Trends API / SerpAPI / pytrends.

### 5. ContentQualityChecker (`app/Services/SeoAgent/ContentQualityChecker.php`)
Cek panjang konten, readability score (Flesch-Kincaid untuk EN, formula sederhana untuk ID).

### 6. SeoAgentController (`app/Http/Controllers/Api/SeoAgentController.php`)
Endpoint webhook Fonnte + endpoint kirim pesan manual.

### 7. SeoAgentLog Model
Menyimpan history chat, command, status, response.

### 8. Background Jobs
- `SeoAgentProcessKeywordJob` — riset keyword antrian
- `SeoAgentProcessContentJob` — generate konten antrian

## Flow Lengkap

### Flow: Riset Keyword
1. User: `riset strategi digital marketing 2026`
2. Controller receive → CommandParser parse → `{type: RESEARCH, keyword: "strategi digital marketing 2026"}`
3. Orchestrator:
   a. Cek `keyword_researches` — sudah pernah riset? jika ya → balas "Sudah pernah diriset. Data: ..."
   b. Jika baru → dispatch `SeoAgentProcessKeywordJob`
   c. Reply WA: "Riset untuk 'strategi digital marketing 2026' sedang diproses..."
4. Job selesai → simpan hasil → kirim reply WA via FonnteService

### Flow: Buat Konten
1. User: `buat konten strategi digital marketing 2026`
2. Parse command → `{type: GENERATE_CONTENT, keyword: "strategi digital marketing 2026"}`
3. Orchestrator:
   a. Cek apakah sudah pernah riset keyword ini
   b. Jika belum → riset dulu
   c. Generate konten (3 phase)
   d. Simpan ke `content_generations`
   e. Reply: "Konten berhasil dibuat. ID: 123. Cek panjang & readability dengan perintah `panjang 123` dan `readability 123`"

### Flow: Cek Keyword
1. User: `cek strategi digital marketing 2026`
2. Parse → `{type: CHECK_KEYWORD, keyword: "strategi digital marketing 2026"}`
3. Cek keyword_researches + content_generations
4. Reply: "Keyword 'strategi digital marketing 2026' sudah pernah diriset. Konten dengan ID 123 sudah dipublikasikan di https://example.com/artikel"

## Environment Variables (di .env)
```
FONNTE_TOKEN=your_fonnte_api_token
FONNTE_WEBHOOK_SECRET=optional_secret
SEO_AGENT_ALLOWED_NUMBERS=08123456789,08198765432
```

Atau via Settings DB.

## Command Format Detail

### `trend <keyword>` / `trending <keyword>`
Cek tren keyword di Google:
- Cari keyword apakah naik/turun
- Data: minat dari waktu ke waktu, topik terkait, pertanyaan terkait
- Output via WA: ringkasan tren (maks 2000 karakter WA)

### `riset <keyword>` / `research <keyword>`
Lakukan keyword research lengkap:
- Panggil `KeywordResearchService`
- Output: LSI keywords (min 12), entities (min 7)
- Simpan di `keyword_researches`
- Balas: hasil riset singkat via WA

### `buat konten <keyword>` / `konten <keyword>`
Generate artikel lengkap:
- Cek/debug keyword research
- Panggil ContentGeneratorService (3 phase)
- Simpan di `content_generations`
- Balas: ID konten + perintah lanjutan

### `cek <keyword>`
Cek apakah keyword sudah pernah diriset/dibuat kontennya:
- Cari di keyword_researches & content_generations
- Balas: status lengkap

### `status <id>`
Cek status generation:
- Cek content_generation by ID
- Balas: phase berapa, status apa

### `panjang <id>`
Cek panjang konten:
- Hitung karakter, kata, estimated reading time
- Balas: statistik panjang konten

### `readability <id>`
Cek skor readability:
- Skor 0-100 (semakin tinggi makin mudah dibaca)
- Analisis: struktur kalimat, paragraf, kata sulit
- Balas: skor + saran perbaikan

### `publish <id>`
Publish konten ke WordPress:
- Butuh setup website (API URL, username, app password)
- Cek apakah website sudah terdaftar
- Post via WordPress REST API
- Balas: URL artikel yang dipublikasikan

### `bantuan`
Balas: daftar perintah yang didukung

### `queue` / `hidupkan worker`
Cek status antrian + jalankan worker:
- Cek jumlah pending & failed jobs
- Tampilkan perintah SSH untuk manual
- Coba jalankan `queue:work --stop-when-empty` via `exec()` (jika enabled)
- Balas: status + hasil

### `bantuan`
Balas: daftar perintah yang didukung

## Catatan
- Panjang pesan WA maksimal 1500 karakter
- Owner number di-set via Settings DB
- Semua log disimpan di tabel `seo_agent_logs`
- Webhook endpoint public (no auth) — validasi via sender number + optional secret
- Rate limit per sender: 10 requests/menit

## Server Path
- Project: `/home/belalangturbo/public_html/tools.juki.eu.org/`
- PHP: `ea-php84`
- Cron: `* * * * * cd /home/belalangturbo/public_html/tools.juki.eu.org/; ea-php84 artisan schedule:run >> /dev/null 2>&1`
- Worker manual: `cd /home/belalangturbo/public_html/tools.juki.eu.org/; ea-php84 artisan queue:work --timeout=240 --tries=3`
