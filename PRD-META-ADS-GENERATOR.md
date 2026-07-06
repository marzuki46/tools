# PRD — Meta Ads Image Generator (Juki Digital Marketing)
**Untuk:** juki.eu.org — dibangun sebagai **Laravel Module terpisah** (Modules), sehingga jika ingin dipakai di projek lain tinggal copy foldernya.

---

## 1. Ringkasan Produk

Tool AI untuk generate **foto/creative iklan Meta Ads** (Facebook & Instagram) otomatis dari 1 prompt/produk, langsung jadi 3 ukuran sekaligus, lengkap dengan riwayat & preset yang bisa dipakai ulang. Ini salah satu modul di ekosistem Juki (nanti bisa gandeng modul SEO Generator, dll — makanya cocok dibuat per-modul).

### 1.1 Perbaikan dari model referensi (yang biasa dipakai tools sejenis)

| Kelemahan tools sejenis | Perbaikan di desain ini |
|---|---|
| Generate 1 ukuran, user resize manual | **1x generate → auto-render ke 3 ukuran** dengan safe-zone berbeda per ukuran (bukan cuma stretch/crop asal) |
| Prompt ditulis manual tiap kali | **Prompt Builder terstruktur** (isi form: produk, headline, CTA, vibe, warna brand) → sistem yang rangkai jadi prompt AI |
| Hasil AI tidak konsisten brand | **Brand Kit tersimpan** (logo, palet warna, font) otomatis dipakai tiap generate |
| History generate berantakan/hilang | Tabel riwayat lengkap + tombol **"Generate Ulang"** dan **"Edit dari sini"** |
| Tidak ada kontrol biaya API AI | Sistem **kredit/kuota per generate**, log biaya per provider AI |
| Overlay teks kaku | Teks/CTA/logo di-overlay terpisah dari background AI (bukan minta AI "tulis teks" yang sering typo) |

---

## 2. Spesifikasi Ukuran Iklan Meta

| Placement | Ukuran (px) | Rasio | Kegunaan |
|---|---|---|---|
| Feed (Square) | 1080 × 1080 | 1:1 | Facebook & Instagram Feed |
| Story / Reels | 1080 × 1920 | 9:16 | Instagram/FB Story, Reels |
| Feed Landscape | 1200 × 628 | 1.91:1 | Link Ads, klasik Facebook Feed |

Desain sistem dibuat **generik per-rasio** (bukan hardcode 3 ukuran), jadi kalau nanti Meta nambah placement baru (mis. Carousel 1080×1080 crop khusus), tinggal tambah 1 baris config, bukan ubah kode inti.

---

## 3. Database — Tabel

> Modul ini **tidak membuat tabel `users` sendiri** — tetap pakai tabel `users` milik aplikasi utama (juki.eu.org), modul hanya `belongsTo` via `user_id`. Ini penting supaya modul portable, tidak mengunci ke 1 aplikasi.

### 3.1 `ad_brand_kits`
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| user_id | FK (ke tabel users aplikasi utama) |
| name | varchar | mis. "Brand Kopi Nusantara" |
| logo_path | varchar nullable |
| primary_color | varchar(7) | hex |
| secondary_color | varchar(7) nullable |
| font_family | varchar nullable |
| is_default | boolean |
| timestamps | |

### 3.2 `ad_projects` (grup kerja/campaign)
id, user_id FK, brand_kit_id FK nullable, name, description nullable, timestamps.

### 3.3 `ad_assets` (foto produk mentah yang diupload user, jadi bahan/referensi AI)
id, project_id FK, file_path, original_name, mime_type, size_kb, timestamps.

### 3.4 `ad_presets` (template gaya visual yang bisa dipakai ulang)
id, user_id FK nullable (null = preset global milik owner platform), name, style_tag (mis. `minimalis`, `bold-promo`, `elegant`), prompt_template (text, berisi placeholder `{produk}`, `{headline}`, dll), thumbnail, is_active, timestamps.

### 3.5 `ad_generations` — **tabel inti, ini yang "form save data AI"**
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| project_id | FK ad_projects | |
| user_id | FK | |
| preset_id | FK nullable | |
| asset_id | FK nullable | foto produk sumber, kalau ada |
| input_form | json | **seluruh isian form user**: nama produk, headline, sub-headline, CTA text, target audiens, vibe/mood, catatan tambahan |
| compiled_prompt | text | prompt final yang dikirim ke AI (hasil rangkaian Prompt Builder) |
| ai_provider | varchar | `openai`, `stability`, `gemini`, dll (abstraksi provider, lihat bagian 5) |
| ai_model | varchar | nama model spesifik yang dipakai |
| ai_raw_response | json nullable | metadata mentah dari provider (buat debug/regenerate) |
| seed | varchar nullable | untuk reproduce hasil yang sama |
| base_image_path | varchar nullable | hasil AI sebelum di-overlay teks/logo |
| status | enum('queued','processing','done','failed') | |
| credit_used | int default 1 | |
| moderation_flag | boolean default false | ditandai kalau lolos/gagal filter konten |
| timestamps | |

### 3.6 `ad_exports` (hasil akhir per ukuran, dari 1 generation bisa banyak baris)
| Kolom | Tipe |
|---|---|
| id | bigint PK |
| generation_id | FK ad_generations |
| placement | enum('feed_square','story','feed_landscape') |
| width / height | int |
| final_image_path | varchar | hasil setelah overlay teks/logo/CTA |
| overlay_config | json | posisi teks, ukuran font, warna — supaya bisa di-edit ulang tanpa generate AI baru |
| downloaded_at | timestamp nullable |
| timestamps | |

### 3.7 `ai_usage_logs` (kontrol biaya & kuota, terhubung ke sistem plan yang sudah kita desain sebelumnya)
id, user_id FK, generation_id FK nullable, provider, tokens_or_units, estimated_cost, created_at.

---

## 4. Relasi Antar Tabel

```
users (aplikasi utama)
   │
   ├─1:N── ad_brand_kits
   ├─1:N── ad_projects ──1:N── ad_assets
   │            │
   │            └─1:N── ad_generations ──N:1── ad_presets
   │                          │      │
   │                          │      └─N:1── ad_assets (foto sumber)
   │                          │
   │                          └─1:N── ad_exports  (3 baris per generation = 3 ukuran)
   │
   └─1:N── ai_usage_logs ──N:1── ad_generations
```

---

## 5. Struktur — Dibuat Sebagai Laravel Module Terpisah

**Ya, ini bisa dan disarankan.** Cara kerjanya menggunakan pola arsitektur Modular (misalnya dengan nwidart/laravel-modules atau custom module loader):

### 5.1 Lokasi & Setup

```
Modules/
 └─ MetaAdsImageGenerator/
    ├─ composer.json
    ├─ module.json
    ├─ app/
    │   ├─ Providers/
    │   │   └─ MetaAdsImageGeneratorServiceProvider.php
    │   ├─ Models/
    │   │   ├─ AdBrandKit.php
    │   │   ├─ AdProject.php
    │   │   ├─ AdAsset.php
    │   │   ├─ AdPreset.php
    │   │   ├─ AdGeneration.php
    │   │   └─ AdExport.php
    │   ├─ Services/
    │   │   ├─ AiProviderManager.php      → abstraksi ganti provider AI (OpenAI/Stability/Gemini) tanpa ubah controller
    │   │   ├─ PromptBuilderService.php   → rangkai input_form jadi compiled_prompt
    │   │   ├─ MultiSizeRendererService.php → 1 base image → render 3 ukuran + overlay teks/logo
    │   │   └─ ModerationService.php       → cek prompt/hasil sebelum disimpan permanen
    │   ├─ Jobs/GenerateAdCreativeJob.php  → proses AI di queue, tidak blocking request
    │   ├─ Http/Controllers/Api/
    │   │   ├─ ProjectController.php
    │   │   ├─ GenerateController.php
    │   │   └─ ExportController.php
    │   ├─ Policies/AdProjectPolicy.php
    │   └─ Facades/MetaAdsImageGenerator.php
    ├─ database/migrations/
    ├─ config/meta-ads-image-generator.php → API key provider, kredit default, dsb
    └─ routes/api.php
```

### 5.2 Cara "tinggal panggil" ke aplikasi utama (juki.eu.org)

Karena menggunakan arsitektur Module, ketika ingin dipakai di projek lain:
1. Copy folder `Modules/MetaAdsImageGenerator` ke dalam folder `Modules/` di projek tujuan.
2. Aktifkan modul (jika menggunakan nwidart/laravel-modules):
```bash
php artisan module:enable MetaAdsImageGenerator
php artisan module:migrate MetaAdsImageGenerator
```

**Keuntungan pendekatan ini untuk kasusmu:**
- Tiap tool (Meta Ads Image Generator, SEO Generator, dll nanti) terisolasi dalam folder modulnya masing-masing (`Modules/MetaAdsImageGenerator`, `Modules/SeoGenerator`, dst).
- Sangat *portable*, tinggal copy-paste folder antar projek Laravel tanpa ribet setting composer repository lokal.
- Aplikasi utama (juki.eu.org) baru butuh sentuh **routing & tampilan pemanggil** saja saat integrasi — logic inti sudah selesai & teruji duluan di dalam modul.

---

## 6. Fitur Lengkap

### Fitur User
- **Brand Kit** — simpan logo, warna, font brand (dipakai otomatis tiap generate)
- **Prompt Builder** (form terstruktur, bukan textarea kosong):
  - Nama produk, kategori produk
  - Headline & sub-headline
  - CTA (Beli Sekarang / Daftar / Hubungi Kami, dst — dropdown + custom)
  - Vibe/mood (dropdown: minimalis, bold-promo, elegant, playful)
  - Upload foto produk asli (opsional, jadi referensi AI) atau full AI-generated background
- **Generate 1x → hasil 3 ukuran otomatis** (Feed, Story, Landscape) dalam satu preview
- **Editor overlay ringan** — geser posisi teks/logo/CTA per ukuran tanpa generate ulang AI (pakai `overlay_config` yang tersimpan)
- **Preset tersimpan** — simpan kombinasi gaya yang disukai, pakai ulang untuk campaign berikutnya
- **Riwayat generate** — searchable, tombol "Generate Ulang" (pakai `compiled_prompt` & `seed` yang sama), "Duplikat & Edit"
- **Download** per ukuran atau batch ZIP semua ukuran sekaligus
- **Kredit/kuota** — terintegrasi ke sistem plan (freemium 1x/hari, atau paket berbayar — reuse pola kuota yang sudah kita desain di project undangan)

### Fitur Owner/Admin Platform
- Kelola preset global (gaya visual default yang disediakan platform)
- Kelola provider AI aktif & fallback (kalau provider A down, otomatis pakai provider B)
- Monitoring biaya AI (`ai_usage_logs`) — total cost vs revenue dari kredit terjual
- Moderasi konten (review hasil yang di-flag `moderation_flag = true`)

---

## 7. Keamanan

- **API key provider AI** hanya di `.env`/config server, tidak pernah dikirim ke frontend — semua request AI lewat backend proxy (`AiProviderManager`).
- **Queue untuk generate** (Laravel Horizon) — request AI tidak blocking, dan mencegah 1 user spam generate berkali-kali dalam sedetik (rate limit per user, mis. max 3 job aktif bersamaan).
- **Rate limiting** endpoint generate (`throttle`) berbasis user, bukan cuma IP.
- **Validasi upload foto produk** — cek mime asli (bukan ekstensi), max size, resize otomatis, simpan di disk privat dulu sebelum lolos moderasi.
- **Content moderation** — jalankan `ModerationService` (bisa pakai moderation endpoint dari provider AI atau filter kata terlarang) sebelum hasil disimpan permanen/bisa didownload — cegah platform dipakai generate konten yang melanggar kebijakan iklan Meta (bisa bikin akun iklan klien kena banned).
- **Otorisasi per-resource** — `AdProjectPolicy`: user cuma bisa akses project/generation miliknya sendiri.
- **Signed URL sementara** untuk preview hasil generate sebelum di-export permanen (terutama kalau hasil masih versi watermark/belum "dibeli" kreditnya).
- **Enkripsi field sensitif** — kalau nanti simpan API key custom milik klien (misal klien punya API key OpenAI sendiri), pakai `encrypted` cast di model, jangan plaintext di DB.
- **Audit log generate** — siapa generate apa, kapan, biaya berapa — penting untuk investigasi kalau ada dispute kredit.
- **Isolasi modul** — karena ini modul terpisah, pastikan migration & config-nya **tidak menimpa** tabel/config aplikasi utama (pakai prefix tabel `ad_` dan `ai_` yang konsisten seperti di atas).

---

## 8. Pengembangan (Roadmap)

### Fase 1 — Core Module (3–4 minggu)
- Setup struktur modul + service provider + migration
- `AiProviderManager` dengan 1 provider dulu (pilih 1 yang paling stabil untuk image-gen)
- Prompt Builder + generate 1 ukuran dulu (belum multi-size)
- Riwayat generate dasar

### Fase 2 — Multi-Size & Brand Kit (2–3 minggu)
- `MultiSizeRendererService` — auto-render 3 ukuran dari 1 base image
- Brand Kit (logo/warna/font)
- Overlay editor ringan (posisi teks/CTA/logo)

### Fase 3 — Preset, Kredit & Moderasi (2 minggu)
- Sistem preset (global & personal)
- Integrasi kuota/kredit (reuse pola freemium dari sistem undangan)
- Moderasi konten otomatis

### Fase 4 — Integrasi ke juki.eu.org (1–2 minggu)
- Copy folder modul ke aplikasi utama dan enable
- Bikin halaman/menu pemanggil di dashboard Juki Digital Marketing
- Hubungkan sistem plan/billing Juki dengan kredit generate

### Fase 5 — Pengembangan Lanjutan
- Provider AI kedua sebagai fallback otomatis
- Batch generate (1 campaign → banyak variasi sekaligus, untuk A/B testing iklan)
- Export langsung terhubung ke Meta Ads API (auto-upload creative ke akun iklan, bukan cuma download manual)
- Analitik performa creative (kalau nanti sinkron dengan hasil iklan di Meta Ads Manager)
