<?php

namespace Modules\AgentConnector\Services;

class ProductKnowledgeService
{
    public function modules(): array
    {
        return [
            [
                'name' => 'Keyword Clusters',
                'slug' => 'keyword-clusters',
                'what' => 'Mengelompokkan keyword terkait menjadi cluster dan menjalankan otomasi konten per cluster.',
                'when' => 'Saat user ingin membuat kelompok keyword, melihat cluster, memantau progress, atau menambah keyword.',
                'actions' => [
                    ['action' => 'list', 'desc' => 'Lihat semua cluster', 'params' => [], 'example' => 'daftar cluster saya'],
                    ['action' => 'create', 'desc' => 'Buat cluster baru', 'params' => ['parent_keyword', 'keywords', 'name', 'description', 'schedule', 'image_keyword', 'image_source', 'image_per_article'], 'example' => 'buat cluster "jasa pembuatan website" dengan keyword jasa buat website, harga website company profile'],
                    ['action' => 'create_from_topic', 'desc' => 'Buat beberapa cluster otomatis dari satu topik lewat AI (1 cluster per parent keyword)', 'params' => ['topic', 'parent_count', 'child_count'], 'example' => 'buat cluster belajar SEO website dengan 4 parent dan 5 child per parent'],
                    ['action' => 'detail', 'desc' => 'Lihat detail cluster termasuk progress', 'params' => ['id'], 'example' => 'detail cluster #3'],
                    ['action' => 'start', 'desc' => 'Mulai otomasi cluster', 'params' => ['id'], 'example' => 'aktifkan cluster #3'],
                    ['action' => 'stop', 'desc' => 'Hentikan otomasi cluster', 'params' => ['id'], 'example' => 'berhenti cluster #3'],
                    ['action' => 'add_keyword', 'desc' => 'Tambah keyword ke cluster', 'params' => ['id', 'keyword'], 'example' => 'tambah keyword "harga jasa seo" ke cluster #3'],
                    ['action' => 'status', 'desc' => 'Progress cluster', 'params' => ['id'], 'example' => 'status cluster #3'],
                ],
                'note' => 'schedule: manual | daily | every_6h | every_12h. image_source: duckduckgo | bing | unsplash. Jika user hanya menyebut topik (misal "buat cluster tentang SEO"), gunakan create_from_topic.',
            ],
            [
                'name' => 'Keyword Research',
                'slug' => 'keyword-research',
                'what' => 'Menghasilkan LSI keywords dan entities dari satu target keyword menggunakan AI.',
                'when' => 'Saat user minta riset keyword, cari kata kunci turunan, LSI, atau entities.',
                'actions' => [
                    ['action' => 'research', 'desc' => 'Riset LSI keywords + entities', 'params' => ['keyword' => 'string (wajib)', 'locale' => 'id (default)'], 'example' => 'riset keyword "seo lokal"'],
                ],
                'note' => 'Hasil dipakai sebagai bahan membuat cluster dan konten.',
            ],
            [
                'name' => 'Content Generator',
                'slug' => 'content-generator',
                'what' => 'Membuat artikel lengkap dalam 3 fase: draft, critical questions, dan artikel final plus meta title/description.',
                'when' => 'Saat user minta generate/membuat konten atau artikel dari sebuah keyword.',
                'actions' => [
                    ['action' => 'generate', 'desc' => 'Generate artikel dari keyword', 'params' => ['keyword' => 'string (wajib)', 'locale' => 'id', 'tone' => 'informative | friendly | professional', 'lsi_keywords', 'entities'], 'example' => 'generate konten tentang "cara optimasi on-page seo"'],
                ],
                'note' => 'Output disimpan sebagai ContentGeneration (generation_id) yang bisa dianalisa atau dipublish.',
            ],
            [
                'name' => 'Content Analyzer',
                'slug' => 'content-analyzer',
                'what' => 'Menganalisa kualitas konten: jumlah kata, heading, gambar, link, meta, dan saran perbaikan.',
                'when' => 'Saat user minta analisa konten, cek kualitas artikel, atau review artikel yang sudah dibuat.',
                'actions' => [
                    ['action' => 'analyze', 'desc' => 'Analisa konten', 'params' => ['content' => 'teks (wajib) ATAU generation_id', 'keyword', 'locale'], 'example' => 'analisa konten "artikel ini..."', 'note' => 'content dan generation_id cukup salah satu'],
                ],
                'note' => 'Jika user menyebut hasil generate terakhir tanpa menempel konten, pakai generation_id terakhir user.',
            ],
            [
                'name' => 'WordPress Publisher',
                'slug' => 'wordpress-publisher',
                'what' => 'Mempublish artikel ke WordPress dan upload gambar otomatis.',
                'when' => 'Saat user minta publish/post/terbitkan artikel, atau publikasi konten ke website.',
                'actions' => [
                    ['action' => 'publish', 'desc' => 'Post artikel ke WordPress', 'params' => ['content' => 'teks ATAU generation_id', 'title', 'keyword', 'status' => 'publish (default) | draft'], 'example' => 'publish konten terakhir ke wordpress'],
                ],
                'note' => 'Butuh koneksi WordPress terkonfigurasi di pengaturan.',
            ],
            [
                'name' => 'Image Fetcher',
                'slug' => 'image-fetcher',
                'what' => 'Mencari gambar dari Bing/DuckDuckGo/Google.',
                'when' => 'Saat user minta cari gambar untuk artikel atau keyword tertentu.',
                'actions' => [
                    ['action' => 'search', 'desc' => 'Cari & tampilkan gambar', 'params' => ['keyword' => 'string (wajib)', 'count' => 'int (default 3)', 'source' => 'bing (default) | duckduckgo | google'], 'example' => 'cari gambar "jasa pembuatan website"'],
                ],
                'note' => '',
            ],
            [
                'name' => 'Google Trends',
                'slug' => 'google-trends',
                'what' => 'Mengecek tren keyword di Google.',
                'when' => 'Saat user minta cek tren keyword.',
                'actions' => [
                    ['action' => 'trend', 'desc' => 'Cek tren keyword', 'params' => ['keyword'], 'example' => 'cek tren keyword "seo 2025"'],
                ],
                'note' => 'Belum tersedia tanpa API key eksternal; sarankan keyword-research sebagai alternatif.',
            ],
        ];
    }

    public function workflows(): array
    {
        return [
            [
                'name' => 'Pipeline lengkap konten',
                'steps' => [
                    '1. Riset keyword → keyword-research (dapat LSI + entities)',
                    '2. Buat cluster dari topik → keyword-clusters create_from_topic (topic, parent_count, child_count)',
                    '3. Generate artikel → content-generator (keyword)',
                    '4. Analisa artikel → content-analyzer (generation_id)',
                    '5. Publish ke website → wordpress-publisher (generation_id)',
                ],
            ],
            [
                'name' => 'Konten untuk website',
                'steps' => [
                    '1. Generate artikel dari keyword',
                    '2. Analisa kualitasnya, beri saran perbaikan',
                    '3. Publish hasilnya ke WordPress',
                ],
            ],
            [
                'name' => 'Otomasi cluster',
                'steps' => [
                    '1. Buat cluster (manual atau dari topik)',
                    '2. Aktifkan cluster untuk mulai otomasi',
                    '3. Pantau status/progress cluster',
                    '4. Berhentikan kapan saja',
                ],
            ],
        ];
    }

    public function examples(): array
    {
        return [
            'riset keyword "seo lokal"',
            'buat cluster jasa pembuatan website dengan keyword jasa buat website, harga website',
            'buat cluster belajar SEO website dengan 4 parent dan 5 child per parent',
            'generate konten tentang "cara optimasi on-page seo"',
            'analisa konten terakhir saya',
            'publish konten terakhir ke wordpress',
            'cari gambar "jasa pembuatan website" sebanyak 5 dari bing',
            'cek tren keyword "ai seo"',
            'daftar cluster saya',
            'status cluster #2',
        ];
    }

    public function markdown(): string
    {
        $lines = [];
        $lines[] = 'PANDUAN PRODUK (SISTEM SEO TOOLS):';

        foreach ($this->modules() as $m) {
            $lines[] = '';
            $lines[] = "MODUL: {$m['name']} ({$m['slug']})";
            $lines[] = "  Fungsi: {$m['what']}";
            $lines[] = "  Kapan dipakai: {$m['when']}";
            foreach ($m['actions'] as $a) {
                $params = $a['params'] ? ' | params: ' . implode(', ', array_map(fn ($p, $d) => is_string($p) ? $p : $d, array_keys($a['params']), array_values($a['params']))) : '';
                $lines[] = "  - {$a['action']}: {$a['desc']}{$params}";
                $lines[] = "    Contoh: {$a['example']}";
            }
            if (!empty($m['note'])) {
                $lines[] = "  Catatan: {$m['note']}";
            }
        }

        $lines[] = '';
        $lines[] = 'ALUR KERJA UMUM:';
        foreach ($this->workflows() as $w) {
            $lines[] = "  [{$w['name']}]";
            foreach ($w['steps'] as $s) {
                $lines[] = "    - {$s}";
            }
        }

        return implode("\n", $lines);
    }

    public function examplesMarkdown(): string
    {
        return "CONTOH PERINTAH VALID:\n" . collect($this->examples())->map(fn ($e) => "- \"{$e}\"")->implode("\n");
    }
}
