<?php

namespace Modules\ContentGenerator\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;

class ContentGeneratorService
{
    private ?CommonMarkConverter $markdown = null;

    public array $tokenUsage = ['tokens_in' => 0, 'tokens_out' => 0];

    protected function cfg(string $key, mixed $default = null): mixed
    {
        $db = Setting::getValue("content-generator.{$key}");
        if ($db !== null) {
            return $db;
        }
        return config("content-generator.{$key}", $default);
    }

    public function generatePhase1(string $keyword, string $locale, string $tone, array $lsiKeywords, array $entities, ?int $userId = null, ?\App\Models\BusinessProfile $businessProfile = null, ?int $targetWords = null, ?array $brief = null): string
    {
        $memoryText = '';
        if ($userId) {
            try {
                $memory = app(\Modules\ContentGenerator\Services\MemoryService::class);
                $memories = $memory->retrieveSimilar($keyword, 3, $userId);
                $memoryText = $memory->buildMemoryPrompt($memories);
            } catch (\Exception $e) {
                Log::warning('MemoryService: retrieval failed', ['error' => $e->getMessage()]);
            }
        }

        $businessText = $businessProfile?->toPromptContext() ?? '';
        $briefText = $brief ? $this->buildBriefPromptContext($brief) : '';

        $prompt = $this->buildPhase1Prompt($keyword, $locale, $tone, $lsiKeywords, $entities, $userId, $memoryText, $businessText, $targetWords, $briefText);
        return $this->processContent($this->callAI($prompt));
    }

    public function buildBriefPromptContext(array $brief): string
    {
        $parts = [];

        if (!empty($brief['meta_title'])) {
            $parts[] = "Meta Title yang DITETAPKAN: {$brief['meta_title']}";
        }
        if (!empty($brief['h1_tag'])) {
            $parts[] = "Judul H1 yang HARUS digunakan sebagai judul utama artikel: {$brief['h1_tag']}";
        }
        if (!empty($brief['url_slug'])) {
            $parts[] = "URL Slug yang DITETAPKAN: {$brief['url_slug']}";
        }
        if (!empty($brief['target_audience'])) {
            $parts[] = "Target Audiens Spesifik: {$brief['target_audience']}";
        }
        if (!empty($brief['pain_point'])) {
            $parts[] = "Pain Point (masalah yang harus diselesaikan): {$brief['pain_point']}";
        }
        $localEntities = $brief['local_entities'] ?? [];
        if (!empty($localEntities)) {
            $parts[] = "Entitas Lokal (sematkan secara natural): " . implode(', ', $localEntities);
        }

        if (empty($parts)) {
            return '';
        }

        return "\n\n---\nCONTENT BRIEF (sasaran penulisan, ikuti dengan ketat):\n" . implode("\n", $parts) . "\n---";
    }

    public function buildBrief(string $keyword, string $locale = 'id', int $keywordCount = 10): array
    {
        $prompt = $this->buildBriefPrompt($keyword, $locale, $keywordCount);
        $raw = $this->stripFences($this->callAI($prompt));
        $parsed = json_decode($raw, true);

        if (!is_array($parsed)) {
            Log::warning('ContentGenerator: failed to parse brief', ['raw' => $raw]);
            return $this->fallbackBrief($keyword);
        }

        return [
            'meta_title' => $parsed['meta_title'] ?? null,
            'h1_tag' => $parsed['h1_tag'] ?? null,
            'url_slug' => $parsed['url_slug'] ?? null,
            'target_audience' => $parsed['target_audience'] ?? null,
            'pain_point' => $parsed['pain_point'] ?? null,
            'local_entities' => $parsed['local_entities'] ?? [],
            'keywords' => $parsed['keywords'] ?? [],
            'raw_response' => $parsed,
        ];
    }

    private function buildBriefPrompt(string $keyword, string $locale, int $keywordCount): string
    {
        $langName = $locale === 'en' ? 'English' : 'Bahasa Indonesia';

        return <<<PROMPT
Anda adalah pakar SEO content strategist. Buat CONTENT BRIEF yang tepat sasaran untuk keyword utama, lalu perluas menjadi {$keywordCount} keyword cluster yang saling mendukung.

Target Keyword Utama: {$keyword}
Bahasa: {$langName}

Return ONLY valid JSON (no markdown, no code fences) dengan skema:
{
  "meta_title": "Judul meta maksimal 60 karakter, mengandung keyword utama dan nilai jual",
  "h1_tag": "Judul H1 maksimal 70 karakter, natural, mengandung keyword utama dan varian lokal",
  "url_slug": "url slug singkat tanpa spasi, huruf kecil, pakai tanda hubung",
  "target_audience": "Siapa target pembaca paling spesifik (profesi/role)",
  "pain_point": "Masalah paling nyata yang dialami target audience terkait topik ini",
  "local_entities": ["entitas lokal/geo yang relevan dengan topik (kota, kawasan, pasar, institusi)"],
  "keywords": [
    {"keyword": "long-tail keyword 1", "intent": "pillar|supporting", "priority": 1},
    {"keyword": "long-tail keyword 2", "intent": "pillar|supporting", "priority": 2}
  ]
}

Aturan:
- Tepat {$keywordCount} keyword pada array keywords.
- HANYA SATU keyword dengan intent "pillar" (keyword utama, prioritas 1). Sisanya "supporting" dengan prioritas 2-{$keywordCount}.
- Setiap supporting keyword harus beragam sudut: masalah, solusi, perbandingan, panduan, pertanyaan umum, studi kasus.
- local_entities maksimal 3 entitas, relevan dan bisa diverifikasi.
- meta_title dan h1_tag dalam bahasa {$langName}.
- Keywords cluster dibuat dalam bahasa {$langName}.
PROMPT;
    }

    private function fallbackBrief(string $keyword): array
    {
        return [
            'meta_title' => mb_substr("{$keyword} | Panduan Lengkap", 0, 60),
            'h1_tag' => mb_substr("{$keyword}: Panduan Lengkap", 0, 70),
            'url_slug' => \Illuminate\Support\Str::slug($keyword),
            'target_audience' => 'Pembaca yang mencari informasi terkait topik ini',
            'pain_point' => 'Kesulitan memahami topik dan mencari solusi yang tepat',
            'local_entities' => [],
            'keywords' => [
                ['keyword' => $keyword, 'intent' => 'pillar', 'priority' => 1],
                ['keyword' => "cara {$keyword}", 'intent' => 'supporting', 'priority' => 2],
                ['keyword' => "harga {$keyword}", 'intent' => 'supporting', 'priority' => 3],
                ['keyword' => "{$keyword} terbaik", 'intent' => 'supporting', 'priority' => 4],
                ['keyword' => "rekomendasi {$keyword}", 'intent' => 'supporting', 'priority' => 5],
                ['keyword' => "{$keyword} untuk pemula", 'intent' => 'supporting', 'priority' => 6],
                ['keyword' => "perbandingan {$keyword}", 'intent' => 'supporting', 'priority' => 7],
                ['keyword' => "manfaat {$keyword}", 'intent' => 'supporting', 'priority' => 8],
            ],
            'raw_response' => null,
        ];
    }

    public function generatePhase2(string $phase1Content, string $keyword): array
    {
        $prompt = $this->buildPhase2Prompt($phase1Content, $keyword);
        $raw = $this->callAI($prompt);
        $questions = $this->parseQuestions($this->stripFences($raw));
        return $questions ?: $this->fallbackQuestions($keyword);
    }

    public function generatePhase3(string $phase1Content, array $questions, string $keyword, string $locale = 'id', string $tone = 'informative', array $lsiKeywords = [], array $entities = [], ?int $targetWords = null, ?array $brief = null, array $linkSources = []): string
    {
        $plainText = strip_tags($phase1Content);
        $effectiveLocale = $locale === 'auto' ? $this->detectLanguage($plainText) : $locale;
        $briefText = $brief ? $this->buildBriefPromptContext($brief) : '';
        $prompt = $this->buildPhase3Prompt($plainText, $questions, $keyword, $effectiveLocale, $tone, $lsiKeywords, $entities, $targetWords, $briefText, $linkSources);
        return $this->processContent($this->callAI($prompt));
    }

    private function stripFences(string $text): string
    {
        return trim(preg_replace('/^```(?:markdown|html|md|json)?\s*\n?|\n?\s*```$/i', '', trim($text)));
    }

    private function processContent(string $raw): string
    {
        $content = $this->stripFences($raw);

        if (!$this->markdown) {
            $this->markdown = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        $html = $this->markdown->convert($content)->getContent();

        return strip_tags($html, [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'br', 'hr',
            'ul', 'ol', 'li',
            'a', 'strong', 'em', 'b', 'i', 'u',
            'blockquote', 'pre', 'code', 'kbd',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'img', 'figure', 'figcaption',
            'dl', 'dt', 'dd',
            'sup', 'sub',
            'div', 'span',
        ]);
    }

    private function buildPhase1Prompt(string $keyword, string $locale, string $tone, array $lsiKeywords, array $entities, ?int $userId = null, string $memoryText = '', string $businessText = '', ?int $targetWords = null, string $briefText = ''): string
    {
        $lsiText = '';
        foreach ($lsiKeywords as $lsi) {
            $kw = $lsi['keyword'] ?? $lsi;
            $lsiText .= "- {$kw}\n";
        }

        $entityText = '';
        foreach ($entities as $entity) {
            $name = $entity['name'] ?? $entity;
            $type = $entity['type'] ?? '';
            $mention = $entity['mention'] ?? '';
            $entityText .= "- {$name}" . ($type ? " ({$type})" : '') . ($mention ? ": {$mention}" : '') . "\n";
        }

        $toneLabels = [
            'formal' => 'Formal dan profesional, cocok untuk artikel bisnis atau akademik',
            'informative' => 'Informatif dan edukatif, cocok untuk blog dan artikel panduan',
            'casual' => 'Santai dan ramah, cocok untuk blog personal',
            'persuasive' => 'Persuasif dan meyakinkan, cocok untuk konten marketing',
            'storytelling' => 'Bercerita dan naratif, cocok untuk konten engaging',
            'instructional' => 'Instruksional dan step-by-step, cocok untuk tutorial',
        ];

        $toneDesc = $toneLabels[$tone] ?? $tone;

        $langName = $locale === 'en' ? 'English' : 'Bahasa Indonesia';
        $langRule = $locale === 'en'
            ? 'WRITE 100% IN ENGLISH. Never use Indonesian. The ENTIRE article must be in fluent, natural English.'
            : 'TULIS 100% DALAM BAHASA INDONESIA. Jangan campur dengan bahasa Inggris. Seluruh artikel harus dalam Bahasa Indonesia yang baik dan benar.';

        $minWords = $targetWords ?: 1000;

        return <<<PROMPT
Anda adalah penulis konten profesional. Buat ARTIKEL LENGKAP seperti artikel di blog atau portal berita — BUKAN catatan, BUKAN poin-poin, BUKAN outline.

{$langRule}

Target Keyword: {$keyword}
Bahasa: {$langName}
Nada/Tone: {$toneDesc}

LSI Keywords (wajib digunakan secara natural):
{$lsiText}
Entities (sematkan dalam konteks yang relevan):
{$entityText}

STRUKTUR WAJIB:
Hanya satu `# ` untuk judul utama di awal
`## ` untuk sub-judul (setiap bagian utama)
`### ` untuk sub-sub-judul (jika perlu)
Paragraf dengan kalimat yang mengalir natural, bervariasi antara pendek dan panjang, dipisah baris kosong
`- ` untuk bullet list (jika perlu)
`[teks tautan](https://contoh.com)` untuk link ke sumber relevan (minimal 3 link relevan)

ISI ARTIKEL:
Judul utama (#) yang mengandung keyword target
Paragraf pembuka yang menjelaskan topik dan menarik minat baca
3-5 sub-bagian (##) yang membahas aspek berbeda dari topik
Data, fakta, contoh nyata, atau statistik di setiap sub-bagian
Minimal 3 tautan (link) ke sumber eksternal relevan
Paragraf penutup yang merangkum dan memberi kesimpulan
Call-to-action ringan di akhir (ajakan membaca lebih lanjut)

KETENTUAN:
Minimal {$minWords} kata
Gunakan keyword utama secara natural 3-5 kali dalam artikel
Semua LSI keywords harus muncul minimal sekali
Semua entities harus tersemat dalam konteks relevan
Tulisan mengalir seperti artikel profesional — jangan kaku dan jangan seperti daftar
JANGAN gunakan catatan kaki atau komentar penulis

READABILITY (WAJIB):
- SETIAP paragraf minimal 3 kalimat — jangan ada paragraf 1-2 kalimat
- Kalimat dalam satu paragraf harus TERHUBUNG dengan konjungsi (dan, tetapi, sementara, sehingga, karena, namun, oleh karena itu, selain itu, di sisi lain)
- JANGAN pernah menulis 2+ kalimat pendek beruntun tanpa kata hubung — itu namanya gaya staccato, sangat tidak enak dibaca
- Variasikan panjang kalimat: ada yang pendek (5-8 kata), ada yang sedang (10-15 kata), ada yang panjang (16-25 kata)
- Gunakan kata transisi antarkalimat: "Selain itu...", "Di sisi lain...", "Lebih lanjut...", "Sebagai contoh...", "Pada akhirnya...", "Tak hanya itu..."
- SETIAP heading harus memiliki 2-5 paragraf dengan total minimal 5 kalimat

Contoh format paragraf yang BENAR:
"Industri hiburan Tiongkok telah mengalami transformasi besar-besaran dalam satu dekade terakhir, dan hasilnya kini terlihat jelas di layar kaca maupun platform streaming global. Berkat investasi miliaran dolar dari pemerintah dan perusahaan swasta, kualitas produksi drama-drama Mandarin kini mampu bersaing dengan produksi Hollywood. Selain itu, cerita yang diangkat semakin kompleks dan relevan dengan isu-isu universal, sehingga penonton dari berbagai latar belakang budaya pun merasa terhubung. Tak heran jika popularitas Dracin melonjak drastis di Asia Tenggara, termasuk Indonesia."

Contoh format paragraf yang SALAH (JANGAN):
"Hiburan Tiongkok dominasi layar Asia. Penonton global pilih drama cina. Kualitas produksi naik drastis. Cerita makin kompleks."

{$memoryText}
{$businessText}
{$briefText}
Output HANYA konten artikel dalam format Markdown, tanpa penjelasan tambahan di luar konten.
PROMPT;
    }

    private function buildPhase2Prompt(string $content, string $keyword): string
    {
        return <<<PROMPT
Anda adalah editor konten senior. Berikut adalah artikel tentang "{$keyword}":

{$content}

Buat daftar 5-8 pertanyaan kritis yang mungkin muncul dari pembaca setelah membaca artikel ini.
Pertanyaan harus:
- Relevan dengan topik dan isi artikel
- Mencakup aspek yang belum dijelaskan secara mendalam
- Membantu pembaca mendapatkan pemahaman yang lebih lengkap

Return ONLY valid JSON array of objects, no markdown:
[
  {"question": "Pertanyaan 1?"},
  {"question": "Pertanyaan 2?"}
]
PROMPT;
    }

    private function buildPhase3Prompt(string $phase1Text, array $questions, string $keyword, string $locale = 'id', string $tone = 'informative', array $lsiKeywords = [], array $entities = [], ?int $targetWords = null, string $briefText = '', array $linkSources = []): string
    {
        $questionsText = '';
        foreach ($questions as $q) {
            $qText = $q['question'] ?? $q;
            $questionsText .= "- {$qText}\n";
        }

        $lsiText = '';
        foreach ($lsiKeywords as $lsi) {
            $kw = $lsi['keyword'] ?? $lsi;
            $lsiText .= "- {$kw}\n";
        }

        $entityText = '';
        foreach ($entities as $entity) {
            $name = $entity['name'] ?? $entity;
            $type = $entity['type'] ?? '';
            $mention = $entity['mention'] ?? '';
            $entityText .= "- {$name}" . ($type ? " ({$type})" : '') . ($mention ? ": {$mention}" : '') . "\n";
        }

        $wordCount = str_word_count($phase1Text);
        if ($targetWords) {
            $targetWords = max((int) $targetWords, 100);
        } else {
            $targetWords = max((int) ($wordCount * 2.0), 1200);
        }
        $isBlank = trim($phase1Text) === '';

        $toneLabels = [
            'formal' => 'Formal dan profesional, cocok untuk artikel bisnis atau akademik',
            'informative' => 'Informatif dan edukatif, cocok untuk blog dan artikel panduan',
            'casual' => 'Santai dan ramah, cocok untuk blog personal',
            'persuasive' => 'Persuasif dan meyakinkan, cocok untuk konten marketing',
            'storytelling' => 'Bercerita dan naratif, cocok untuk konten engaging',
            'instructional' => 'Instruksional dan step-by-step, cocok untuk tutorial',
        ];

        $toneDesc = $toneLabels[$tone] ?? $tone;
        $langName = $locale === 'en' ? 'English' : 'Bahasa Indonesia';
        $langRule = $locale === 'en'
            ? 'WRITE 100% IN ENGLISH. Never use Indonesian. The ENTIRE article must be in fluent, natural English.'
            : 'TULIS 100% DALAM BAHASA INDONESIA. Jangan campur dengan bahasa Inggris. Seluruh artikel harus dalam Bahasa Indonesia yang baik dan benar.';

        $blankNote = $isBlank
            ? "Konten awal kosong. Buat seluruh artikel baru berdasarkan judul/keyword berikut dan informasi bisnis (jika ada): {$keyword}"
            : "Kamu adalah AI. Tambah dan kembangkan konten berikut sehingga mencapai {$targetWords} kata. Kamu bisa mengembangkan setiap bagian dengan detail, data, contoh, dan informasi baru tanpa mengubah makna yang sudah ada.";

        $linkText = '';
        foreach ($linkSources as $ls) {
            $lsTitle = $ls['title'] ?? '';
            $lsSlug = $ls['slug'] ?? '';
            $lsUrl = $ls['url'] ?? ($lsSlug ? "/{$lsSlug}/" : '');
            $lsKeyword = $ls['keyword'] ?? '';
            if ($lsTitle && $lsUrl) {
                $linkText .= "- {$lsTitle} → {$lsUrl}" . ($lsKeyword ? " (relevan utk: {$lsKeyword})" : '') . "\n";
            }
        }
        if ($linkText !== '') {
            $linkText = rtrim($linkText, "\n");
        }

        return <<<PROMPT
Anda adalah penulis konten profesional. Buat ARTIKEL LENGKAP seperti artikel di blog atau portal berita — BUKAN catatan, BUKAN poin-poin, BUKAN outline.

{$langRule}

Target Keyword: {$keyword}
Bahasa: {$langName}
Nada/Tone: {$toneDesc}
TARGET JUMLAH KATA: minimal {$targetWords} kata

{$blankNote}

KONTEN AWAL (gunakan sebagai fondasi, lalu KEMBANGKAN):
{$phase1Text}

PERTANYAAN KRITIS (jawab dan jadikan SUB-BAGIAN BARU dalam artikel, BUKAN format Q&A):
{$questionsText}

LSI Keywords (wajib digunakan secara natural):
{$lsiText}
Entities (sematkan dalam konteks yang relevan):
{$entityText}

SUMBER TAUTAN INTERNAL (wajib: pilih MINIMAL 3 yang paling relevan dengan topik, lalu tautkan dengan anchor text natural di tengah kalimat):
{$linkText}

STRUKTUR WAJIB:
Hanya satu `# ` untuk judul utama di awal
`## ` untuk sub-judul (setiap bagian utama)
`### ` untuk sub-sub-judul (jika perlu)
Paragraf dengan kalimat yang mengalir natural, bervariasi antara pendek dan panjang, dipisah baris kosong
`- ` untuk bullet list (jika perlu)
`[teks tautan](https://contoh.com)` untuk link ke sumber relevan (minimal 3 link relevan)

ISI ARTIKEL:
Judul utama (#) yang mengandung keyword target
Paragraf pembuka yang menjelaskan topik dan menarik minat baca
KEMBANGKAN setiap bagian dari konten awal dengan detail, contoh, dan data baru
Setiap pertanyaan kritis jadikan SATU SUB-BAGIAN (##) baru — dikembangkan penuh dengan paragraf
Minimal 3 tautan (link) ke sumber eksternal relevan
Paragraf penutup yang merangkum dan memberi kesimpulan
Call-to-action ringan di akhir

KETENTUAN:
Minimal {$targetWords} kata (lebih panjang dari konten awal)
Gunakan keyword utama secara natural 3-5 kali dalam artikel
Semua LSI keywords harus muncul minimal sekali
Semua entities harus tersemat dalam konteks relevan
Tulisan mengalir seperti artikel profesional — jangan kaku dan jangan seperti daftar
JANGAN gunakan format Q&A — integrasikan semua dalam alur artikel naratif
JANGAN ada catatan kaki, komentar penulis, atau metadata apapun

READABILITY (WAJIB):
- SETIAP paragraf minimal 3 kalimat — jangan ada paragraf 1-2 kalimat
- Kalimat dalam satu paragraf harus TERHUBUNG dengan konjungsi (dan, tetapi, sementara, sehingga, karena, namun, oleh karena itu, selain itu, di sisi lain)
- JANGAN pernah menulis 2+ kalimat pendek beruntun tanpa kata hubung — itu namanya gaya staccato, sangat tidak enak dibaca
- Variasikan panjang kalimat: ada yang pendek (5-8 kata), ada yang sedang (10-15 kata), ada yang panjang (16-25 kata)
- Gunakan kata transisi antarkalimat: "Selain itu...", "Di sisi lain...", "Lebih lanjut...", "Sebagai contoh...", "Pada akhirnya...", "Tak hanya itu..."
- SETIAP heading harus memiliki 2-5 paragraf dengan total minimal 5 kalimat

Contoh format paragraf yang BENAR:
"Industri hiburan Tiongkok telah mengalami transformasi besar-besaran dalam satu dekade terakhir, dan hasilnya kini terlihat jelas di layar kaca maupun platform streaming global. Berkat investasi miliaran dolar dari pemerintah dan perusahaan swasta, kualitas produksi drama-drama Mandarin kini mampu bersaing dengan produksi Hollywood. Selain itu, cerita yang diangkat semakin kompleks dan relevan dengan isu-isu universal, sehingga penonton dari berbagai latar belakang budaya pun merasa terhubung. Tak heran jika popularitas Dracin melonjak drastis di Asia Tenggara, termasuk Indonesia."

Contoh format paragraf yang SALAH (JANGAN):
"Hiburan Tiongkok dominasi layar Asia. Penonton global pilih drama cina. Kualitas produksi naik drastis. Cerita makin kompleks."

{$briefText}
Output HANYA konten artikel dalam format Markdown, tanpa penjelasan tambahan di luar konten.
PROMPT;
    }

    public function generateMetaData(string $phase3Content, string $keyword, string $locale = 'id'): array
    {
        $plainText = strip_tags($phase3Content);
        $effectiveLocale = $locale === 'auto' ? $this->detectLanguage($plainText) : $locale;
        $preview = mb_substr($plainText, 0, 3000);

        $prompt = <<<PROMPT
Anda adalah SEO specialist. Buat META TITLE dan META DESCRIPTION dengan fokus HIGH CTR dari konten berikut.

Keyword Target: {$keyword}
Bahasa: {$effectiveLocale}

KONTEN:
{$preview}

KETENTUAN META TITLE:
- Maksimal 60 karakter
- Mengandung keyword target di awal
- Gunakan angka, power words, atau tanda kurung/bracket untuk增加CTR (contoh: "Cara... [Panduan 2026]", "7 Tips...")
- Buat pembaca penasaran dan ingin klik
- Jangan clickbait palsu, tetap relevan dengan konten

KETENTUAN META DESCRIPTION:
- 150-160 karakter
- Mengandung keyword target 1-2 kali secara natural
- Awali dengan value proposition / manfaat
- Akhiri dengan micro-CTA (contoh: "Simak selengkapnya!", "Pelajari caranya!")
- Baca selengkapnya di artikel bukan merupakan CTA
- Buat pembaca yakin konten ini solusi untuk mereka

Return ONLY valid JSON, no markdown:
{"title": "Meta Title disini", "description": "Meta description disini."}
PROMPT;

        $raw = $this->callAI($prompt);
        $cleaned = $this->stripFences($raw);
        $decoded = json_decode($cleaned, true);

        if ($decoded && isset($decoded['title'], $decoded['description'])) {
            return [
                'title' => mb_substr(trim($decoded['title']), 0, 65),
                'description' => mb_substr(trim($decoded['description']), 0, 165),
            ];
        }

        preg_match('/"title"\s*:\s*"([^"]+)"/', $cleaned, $t);
        preg_match('/"description"\s*:\s*"([^"]+)"/', $cleaned, $d);
        return [
            'title' => mb_substr(trim($t[1] ?? $keyword), 0, 65),
            'description' => mb_substr(trim($d[1] ?? substr($plainText, 0, 160)), 0, 165),
        ];
    }

    private function callAI(string $prompt): string
    {
        $ai = Setting::aiConfig();
        $url = $ai['url'];
        $apiKey = $ai['api_key'];
        $model = $ai['chat_model'];

        if (!$url) {
            throw new Exception('AI URL is not configured.');
        }

        $delay = (int) $this->cfg('request_delay', 2);
        if ($delay > 0) {
            sleep($delay);
        }

        $customInstructions = Setting::getValue('ai.system_prompt');
        if ($customInstructions) {
            $prompt .= "\n\n---\nINSTRUKSI KUSTOM:\n{$customInstructions}";
        }

        $endpoint = str_ends_with(rtrim($url, '/'), '/v1') ? rtrim($url, '/') . '/chat/completions' : rtrim($url, '/') . '/v1/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Anda adalah asisten penulis konten profesional. Selalu gunakan format Markdown untuk struktur artikel.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 16384,
            'stream' => false,
        ];

        $maxAttempts = 3;
        $response = null;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(300)
                    ->connectTimeout(30)
                    ->withHeaders([
                        'Authorization' => $apiKey ? "Bearer {$apiKey}" : '',
                        'Content-Type' => 'application/json',
                    ])->post($endpoint, $payload);

                if ($response->successful()) {
                    break;
                }

                $lastError = new Exception('AI HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 300));
            } catch (\Throwable $e) {
                $lastError = $e;
            }

            if ($attempt < $maxAttempts) {
                Log::warning('ContentGenerator: retry callAI', ['attempt' => $attempt, 'error' => $lastError->getMessage()]);
                sleep(5 * $attempt);
            }
        }

        if (!$response || !$response->successful()) {
            Log::error('ContentGenerator AI Failed', [
                'error' => $lastError?->getMessage(),
            ]);
            throw new Exception('Gagal memproses konten. Silakan coba lagi.');
        }

        $data = $response->json();

        $usage = $data['usage'] ?? [];
        $this->tokenUsage['tokens_in'] += $usage['prompt_tokens'] ?? 0;
        $this->tokenUsage['tokens_out'] += $usage['completion_tokens'] ?? 0;

        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function parseQuestions(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        preg_match_all('/"\s*question\s*"\s*:\s*"([^"]+)"/', $raw, $matches);
        if (!empty($matches[1])) {
            return array_map(fn($q) => ['question' => $q], $matches[1]);
        }

        return [];
    }

    private function detectLanguage(string $text): string
    {
        $idWords = ['yang', 'dan', 'di ', 'ke ', 'dari', 'dengan', 'untuk', 'pada', 'adalah', 'ini', 'itu', 'tidak', 'akan', 'dalam', 'juga', 'ada', 'karena', 'seperti', 'atau', 'sudah', 'belum', 'bisa', 'dapat', 'lebih', 'dibuat', 'setiap', 'pengguna', 'hanya', 'untuk', 'sebuah', 'tersebut', 'menjadi'];
        $enWords = ['the', 'and', 'of', 'to', 'in', 'is', 'are', 'for', 'with', 'that', 'this', 'you', 'your', 'it', 'on', 'as', 'by', 'from', 'at', 'be', 'not', 'we', 'our', 'will', 'can', 'have', 'has', 'their', 'more', 'about', 'into', 'such', 'these', 'those'];

        $lower = mb_strtolower($text);

        $idCount = 0;
        foreach ($idWords as $w) {
            $idCount += substr_count($lower, $w);
        }

        $enCount = 0;
        foreach ($enWords as $w) {
            $enCount += substr_count($lower, $w);
        }

        if ($idCount > $enCount) {
            return 'id';
        }
        if ($enCount > $idCount) {
            return 'en';
        }

        return 'id';
    }

    private function fallbackQuestions(string $keyword): array
    {
        return [
            ['question' => "Apa itu {$keyword}?"],
            ['question' => "Mengapa {$keyword} penting?"],
            ['question' => "Bagaimana cara memulai dengan {$keyword}?"],
            ['question' => "Apa saja tantangan utama {$keyword}?"],
            ['question' => "Apa masa depan {$keyword}?"],
        ];
    }
}
