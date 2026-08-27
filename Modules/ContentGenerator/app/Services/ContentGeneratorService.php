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

    public function resolveLocale(?string $locale, ?\App\Models\ApiKeyWebsite $website = null, string $text = ''): string
    {
        $locale = trim((string) $locale);
        if (in_array($locale, ['id', 'en'], true)) {
            return $locale;
        }
        if ($website && in_array($website->locale, ['id', 'en'], true)) {
            return $website->locale;
        }
        if (trim($text) !== '') {
            return $this->detectLanguage($text);
        }
        return 'id';
    }

    public function generatePhase1(string $keyword, string $locale, string $tone, array $lsiKeywords, array $entities, ?int $userId = null, ?\App\Models\BusinessProfile $businessProfile = null, ?int $targetWords = null, ?array $brief = null, ?bool $includeExternalLinks = null): string
    {
        $effectiveLocale = $this->resolveLocale($locale);
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

        $prompt = $this->buildPhase1Prompt($keyword, $effectiveLocale, $tone, $lsiKeywords, $entities, $userId, $memoryText, $businessText, $targetWords, $briefText, $includeExternalLinks);
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

    public function generatePhase3(string $phase1Content, array $questions, string $keyword, string $locale = 'id', string $tone = 'informative', array $lsiKeywords = [], array $entities = [], ?int $targetWords = null, ?array $brief = null, array $linkSources = [], ?\App\Models\BusinessProfile $businessProfile = null, ?bool $includeExternalLinks = null, ?\App\Models\ApiKeyWebsite $website = null, string $contentType = 'post'): string
    {
        $plainText = strip_tags($phase1Content);
        $effectiveLocale = $this->resolveLocale($locale, $website, $plainText);
        $briefText = $brief ? $this->buildBriefPromptContext($brief) : '';
        $businessText = $businessProfile?->toPromptContext() ?? '';

        $slop = app(NoAiSlopService::class);
        $rewriteEnabled = (bool) $this->cfg('ai_slop.rewrite_enabled', true);
        $maxRetries = max(1, (int) $this->cfg('ai_slop.max_retries', 2));
        $autoFix = (bool) $this->cfg('ai_slop.auto_fix_banned_words', false);

        $prompt = $this->buildPhase3Prompt($plainText, $questions, $keyword, $effectiveLocale, $tone, $lsiKeywords, $entities, $targetWords, $briefText, $linkSources, $businessText, $includeExternalLinks, $contentType);
        $content = $this->processContent($this->callAI($prompt));

        $attempt = 1;
        while ($attempt <= $maxRetries && $rewriteEnabled) {
            $hits = $slop->scan($content, $effectiveLocale);
            if (!$slop->shouldRewrite($hits, $effectiveLocale)) {
                break;
            }

            Log::warning('NoAiSlopService: rewrite triggered', [
                'keyword' => $keyword,
                'locale' => $effectiveLocale,
                'attempt' => $attempt,
                'max_retries' => $maxRetries,
                'count' => count($hits),
                'score' => $slop->score($hits, $effectiveLocale),
                'hits' => array_slice($hits, 0, 20),
            ]);

            $fixPrompt = $this->buildPhase3FixPrompt($content, $hits, $keyword, $effectiveLocale, $tone);
            $content = $this->processContent($this->callAI($fixPrompt));
            $attempt++;
        }

        if ($autoFix) {
            $content = $slop->clean($content, $effectiveLocale, true);
        }

        return $content;
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

    private function buildPhase1Prompt(string $keyword, string $locale, string $tone, array $lsiKeywords, array $entities, ?int $userId = null, string $memoryText = '', string $businessText = '', ?int $targetWords = null, string $briefText = '', ?bool $includeExternalLinks = null): string
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
        $externalLinkRule = $this->externalLinkRule($includeExternalLinks);

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
`[teks tautan](https://contoh.com)` untuk link ke sumber relevan

ISI ARTIKEL:
Judul utama (#) yang mengandung keyword target
Paragraf pembuka yang menjelaskan topik dan menarik minat baca
3-5 sub-bagian (##) yang membahas aspek berbeda dari topik
Data, fakta, contoh nyata, atau statistik di setiap sub-bagian
{$externalLinkRule}
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
- Gunakan kata transisi antarkalimat: "Selain itu...", "Di sisi lain...", "Lebih lanjut...", "Sebagai contoh...", "Tak hanya itu..."
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

    private function externalLinkRule(?bool $includeExternalLinks = null): string
    {
        $enabled = $includeExternalLinks ?? (bool) $this->cfg('include_external_links', true);

        if ($enabled) {
            return "Sertakan MAKSIMAL 1 (satu) tautan eksternal ke sumber yang relevan dan dapat diverifikasi. JANGAN lebih dari satu tautan eksternal.";
        }

        return "JANGAN sertakan tautan eksternal sama sekali. Hanya gunakan tautan internal dari daftar jika tersedia.";
    }

    private function contentTypeRule(string $contentType): string
    {
        return match ($contentType) {
            'product' => 'Buat DESKRIPSI PRODUK untuk WooCommerce — BUKAN artikel blog. Gaya persuasif: manfaat utama, keunggulan, spesifikasi/bahan/ukuran (jika relevan), cara pakai singkat, dan ajakan membeli. Struktur bebas dengan sub-judul (##) yang menjual, bukan struktur artikel berita.',
            'product_cat' => 'Buat DESKRIPSI KATEGORI PRODUK untuk halaman arsip WooCommerce. Jelaskan tema/koleksi kategori ini, manfaat berbelanja di kategori ini, jenis produk yang tersedia, dan panduan singkat memilih. Akhiri dengan ajakan menjelajahi produk di kategori ini.',
            'tag' => 'Buat DESKRIPSI TAG untuk halaman arsip tag. Perkenalkan topik tag secara menarik, jelaskan hubungannya dengan konten lain di situs, dan apa yang pembaca temukan di arsip ini.',
            default => 'Buat ARTIKEL LENGKAP seperti artikel di blog atau portal berita — BUKAN catatan, BUKAN poin-poin, BUKAN outline.',
        };
    }

    private function antiSlopRuleBlock(string $locale): string
    {
        if ($locale !== 'en') {
            return '';
        }

        return <<<RULE
ENGLISH ANTI-AI-SLOP (MANDATORY):
- NEVER use these empty words: delve, foster, leverage, utilize, facilitate, empower, streamline, robust, cutting-edge, paradigm shift, game changer, transformative, elevate, embark, supercharge, harness, ever-evolving, tapestry, realm, beacon.
- NEVER use filler phrases: "it's worth noting", "it's important to note", "at the end of the day", "when it comes to", "in today's world", "the reality is", "in this article", "let's dive in".
- NEVER use em dashes (—) or en dashes (–). Replace them with periods, commas, colons, or parentheses.
- NEVER use vague attribution like "experts believe" or "industry reports" without a real, named source.
- NEVER use chatbot jargon like "I hope this helps" or "let me know if".
- Avoid copula substitutes: "serves as", "boasts", "stands as" — prefer "is" and "has".
- Never end with a generic pep-talk like "the future looks bright".
- NEVER open with throat-clearing: "Here's the thing", "Let me be clear", "The uncomfortable truth is".
- Avoid binary contrasts ("It's not X, it's Y"), colon reveals, fake-profound kickers, and synonym cycling.
- Do not invent facts, names, dates, or citations that are not present in the source text.
- Write like a human: active voice, specific, concrete, straight to the point.

RULE;
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

    private function buildPhase3Prompt(string $phase1Text, array $questions, string $keyword, string $locale = 'id', string $tone = 'informative', array $lsiKeywords = [], array $entities = [], ?int $targetWords = null, string $briefText = '', array $linkSources = [], string $businessText = '', ?bool $includeExternalLinks = null, string $contentType = 'post'): string
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
        $hasHomeLink = false;
        $hasCategoryLink = false;
        foreach ($linkSources as $ls) {
            $lsTitle = $ls['title'] ?? '';
            $lsSlug = $ls['slug'] ?? '';
            $lsUrl = $ls['url'] ?? ($lsSlug ? "/{$lsSlug}/" : '');
            $lsKeyword = $ls['keyword'] ?? '';
            $lsType = $ls['type'] ?? 'post';
            if ($lsType === 'home') $hasHomeLink = true;
            if ($lsType === 'category') $hasCategoryLink = true;
            if ($lsTitle && $lsUrl) {
                $typeTag = $lsType === 'home' ? ' [Beranda]' : ($lsType === 'category' ? ' [Kategori]' : '');
                $linkText .= "- {$lsTitle} → {$lsUrl}{$typeTag}" . ($lsKeyword ? " (relevan utk: {$lsKeyword})" : '') . "\n";
            }
        }
        if ($linkText !== '') {
            $linkText = rtrim($linkText, "\n");
            $anchorRules = '';
            if ($hasHomeLink) {
                $anchorRules .= "\n- Sertakan tautan ke Beranda TEPAT 1x. Anchor text = nama brand/situs persis seperti di daftar.";
            }
            if ($hasCategoryLink) {
                $anchorRules .= "\n- Sertakan tautan ke halaman Kategori TEPAT 1x. Anchor text = nama kategori persis seperti di daftar.";
            }
            $linkRule = "SUMBER TAUTAN INTERNAL (HANYA BOLEH memakai URL dari daftar ini — pilih 3-5 yang paling relevan, tautkan dengan anchor text natural di tengah kalimat):\n{$linkText}\n\nATURAN TAUTAN INTERNAL (WAJIB):\n- DILARANG KERAS membuat, menebak, atau mengarang URL internal lain yang tidak ada di daftar di atas.\n- Jika tidak ada yang relevan sama sekali, jangan buat tautan internal.\n- URL harus ditulis PERSIS seperti di daftar.{$anchorRules}\n- Aturan ini berlaku apa pun bahasa konten.";
        } else {
            $linkRule = "TAUTAN INTERNAL: Tidak ada daftar URL internal yang tersedia. JANGAN membuat tautan internal apa pun. Jangan mengarang URL. Aturan ini berlaku apa pun bahasa konten.";
        }
        $typeRule = $this->contentTypeRule($contentType);
        $externalLinkRule = $this->externalLinkRule($includeExternalLinks);
        $slopRule = $this->antiSlopRuleBlock($locale);

        return <<<PROMPT
Anda adalah penulis konten profesional. {$typeRule}

{$langRule}
SEMUA ATURAN DALAM PROMPT INI WAJIB DIPATUHI APA PUN BAHASA KONTEN YANG DIMINTA.

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

{$linkRule}

STRUKTUR WAJIB:
Hanya satu `# ` untuk judul utama di awal
`## ` untuk sub-judul (setiap bagian utama)
`### ` untuk sub-sub-judul (jika perlu)
Paragraf dengan kalimat yang mengalir natural, bervariasi antara pendek dan panjang, dipisah baris kosong
`- ` untuk bullet list (jika perlu)
`[teks tautan](https://contoh.com)` untuk link ke sumber relevan

ISI ARTIKEL:
Judul utama (#) yang mengandung keyword target
Paragraf pembuka yang menjelaskan topik dan menarik minat baca
KEMBANGKAN setiap bagian dari konten awal dengan detail, contoh, dan data baru
Setiap pertanyaan kritis jadikan SATU SUB-BAGIAN (##) baru — dikembangkan penuh dengan paragraf
{$externalLinkRule}
Paragraf penutup yang memberi kesimpulan KONKRET atau takeaway praktis — JANGAN sekadar mengulang isi artikel
Call-to-action ringan di akhir

KETENTUAN:
Minimal {$targetWords} kata (lebih panjang dari konten awal)
Gunakan keyword utama secara natural 3-5 kali dalam artikel
Semua LSI keywords harus muncul minimal sekali
Semua entities harus tersemat dalam konteks relevan
Tulisan mengalir seperti artikel profesional — jangan kaku dan jangan seperti daftar
JANGAN gunakan format Q&A — integrasikan semua dalam alur artikel naratif
JANGAN ada catatan kaki, komentar penulis, atau metadata apapun

GAYA PENULISAN (HINDARI AI-SLOP — WAJIB):
JANGAN gunakan kata-kata isi angin: memfasilitasi, memberdayakan, transformatif, revolusioner, mutakhir, komprehensif, pergeseran paradigma, "penting untuk dicatat", "perlu digarisbawahi", "di era modern", "mari kita bahas", "dalam artikel ini".
JANGAN buka kalimat dengan basa-basi: "Hal yang tidak banyak orang tahu", "Yang paling sering diabaikan", "Bagian yang jarang dibahas".
JANGAN pakai kontras biner ("Ini bukan X, melainkan Y"), colon reveal dramatis ("Kuncinya: ..."), em dash (—), atau kalimat sok-dalam di akhir.
JANGAN pakai atribusi samar seperti "para ahli" atau "banyak pihak", dan jangan memakai jargon obrolan seperti "Semoga membantu".
JANGAN ulangi sinonim secara bergaya; pakai kata yang jelas dan konsisten.
JANGAN menambahkan fakta, nama, tanggal, angka, atau kutipan yang tidak ada pada konten sumber.
Tulis seperti manusia: langsung ke poin, kalimat aktif, spesifik, dan natural.

{$slopRule}

READABILITY (WAJIB):
- SETIAP paragraf minimal 3 kalimat — jangan ada paragraf 1-2 kalimat
- Kalimat dalam satu paragraf harus TERHUBUNG dengan konjungsi (dan, tetapi, sementara, sehingga, karena, namun, oleh karena itu, selain itu, di sisi lain)
- JANGAN pernah menulis 2+ kalimat pendek beruntun tanpa kata hubung — itu namanya gaya staccato, sangat tidak enak dibaca
- Variasikan panjang kalimat: ada yang pendek (5-8 kata), ada yang sedang (10-15 kata), ada yang panjang (16-25 kata)
- Gunakan kata transisi antarkalimat: "Selain itu...", "Di sisi lain...", "Lebih lanjut...", "Sebagai contoh...", "Tak hanya itu..."
- SETIAP heading harus memiliki 2-5 paragraf dengan total minimal 5 kalimat

Contoh format paragraf yang BENAR:
"Industri hiburan Tiongkok telah mengalami transformasi besar-besaran dalam satu dekade terakhir, dan hasilnya kini terlihat jelas di layar kaca maupun platform streaming global. Berkat investasi miliaran dolar dari pemerintah dan perusahaan swasta, kualitas produksi drama-drama Mandarin kini mampu bersaing dengan produksi Hollywood. Selain itu, cerita yang diangkat semakin kompleks dan relevan dengan isu-isu universal, sehingga penonton dari berbagai latar belakang budaya pun merasa terhubung. Tak heran jika popularitas Dracin melonjak drastis di Asia Tenggara, termasuk Indonesia."

Contoh format paragraf yang SALAH (JANGAN):
"Hiburan Tiongkok dominasi layar Asia. Penonton global pilih drama cina. Kualitas produksi naik drastis. Cerita makin kompleks."

{$businessText}

{$briefText}
Output HANYA konten artikel dalam format Markdown, tanpa penjelasan tambahan di luar konten.
PROMPT;
    }

    private function buildPhase3FixPrompt(string $currentContent, array $hits, string $keyword, string $locale, string $tone): string
    {
        $langName = $locale === 'en' ? 'English' : 'Bahasa Indonesia';
        $langRule = $locale === 'en'
            ? 'WRITE 100% IN ENGLISH. Never use Indonesian.'
            : 'TULIS 100% DALAM BAHASA INDONESIA. Jangan campur dengan bahasa Inggris.';

        $hitLines = '';
        foreach ($hits as $hit) {
            $hitLines .= "- [{$hit['pattern']}] (baris {$hit['line']}): " . trim($hit['snippet'] ?? '') . "\n";
        }

        $slopRule = $this->antiSlopRuleBlock($locale);

        $idRules = $locale === 'en' ? '' : <<<RULES
GAYA PENULISAN (HINDARI AI-SLOP — WAJIB):
- JANGAN gunakan kata-kata isi angin: memfasilitasi, memberdayakan, transformatif, revolusioner, mutakhir, komprehensif, pergeseran paradigma, "penting untuk dicatat", "perlu digarisbawahi", "di era modern", "mari kita bahas", "dalam artikel ini".
- JANGAN pakai kontras biner ("Ini bukan X, melainkan Y"), colon reveal dramatis, em dash (—), atau kalimat sok-dalam.
- JANGAN gunakan atribusi samar seperti "para ahli", "banyak pihak", atau jargon obrolan seperti "Semoga membantu".
- Tulis seperti manusia: kalimat aktif, spesifik, dan langsung ke poin.

RULES;

        return <<<PROMPT
Anda adalah editor penulis senior. Artikel berikut mengandung pola penulisan yang khas buatan AI (AI-slop). Perbaiki HANYA pola yang terdeteksi berikut ini, tanpa mengubah fakta, makna, struktur paragraf, keyword, tautan, atau jumlah kata secara drastis.

Keyword: {$keyword}
Bahasa: {$langName}
Nada: {$tone}

{$langRule}

POLA YANG TERDETEKSI (perbaiki semua ini):
{$hitLines}

{$idRules}
{$slopRule}

ATURAN PENTING:
- JANGAN menambahkan fakta, nama, tanggal, angka, kutipan, atau referensi baru yang tidak ada di teks.
- Pertahankan semua heading, paragraf, dan informasi yang sudah benar.
- Perbaiki dengan mengganti frasa/kalimat yang bermasalah dengan versi yang lebih natural, bukan menghapus informasi penting.
- Output HANYA konten artikel hasil perbaikan dalam format Markdown, tanpa penjelasan tambahan.

KONTEN SAAT INI:
{$currentContent}
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
                'title' => \App\Support\SeoText::capTitle(trim($decoded['title']), 60, $keyword),
                'description' => \App\Support\SeoText::capDescription(trim($decoded['description']), 160),
            ];
        }

        preg_match('/"title"\s*:\s*"([^"]+)"/', $cleaned, $t);
        preg_match('/"description"\s*:\s*"([^"]+)"/', $cleaned, $d);
        return [
            'title' => \App\Support\SeoText::capTitle(trim($t[1] ?? $keyword), 60, $keyword),
            'description' => \App\Support\SeoText::capDescription(trim($d[1] ?? substr($plainText, 0, 160)), 160),
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

                $body = $response->body();
                // Quota habis (402 MONTHLY_REQUEST_COUNT) — jangan retry cepat, biarkan job di-release 1 jam oleh caller
                if (str_contains($body, 'MONTHLY_REQUEST_COUNT') || $response->status() === 402) {
                    $lastError = new Exception('AI quota habis (MONTHLY_REQUEST_COUNT): ' . substr($body, 0, 300));
                    break;
                }
                $lastError = new Exception('AI HTTP ' . $response->status() . ': ' . substr($body, 0, 300));
            } catch (\Throwable $e) {
                $lastError = $e;
            }

            if ($attempt < $maxAttempts && !str_contains($lastError->getMessage(), 'MONTHLY_REQUEST_COUNT')) {
                Log::warning('ContentGenerator: retry callAI', ['attempt' => $attempt, 'error' => $lastError->getMessage()]);
                sleep(5 * $attempt);
            } elseif (str_contains($lastError->getMessage(), 'MONTHLY_REQUEST_COUNT')) {
                break;
            }
        }

        if (!$response || !$response->successful()) {
            Log::error('ContentGenerator AI Failed', [
                'error' => $lastError?->getMessage(),
            ]);
            if (str_contains($lastError->getMessage(), 'MONTHLY_REQUEST_COUNT')) {
                throw new Exception('AI quota habis (MONTHLY_REQUEST_COUNT) — ganti model di Settings/AI atau tunggu reset. ' . substr($lastError->getMessage(), 0, 200));
            }
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
