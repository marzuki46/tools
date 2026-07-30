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

    public function generatePhase1(string $keyword, string $locale, string $tone, array $lsiKeywords, array $entities, ?int $userId = null, ?\App\Models\BusinessProfile $businessProfile = null): string
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

        $prompt = $this->buildPhase1Prompt($keyword, $locale, $tone, $lsiKeywords, $entities, $userId, $memoryText, $businessText);
        return $this->processContent($this->callAI($prompt));
    }

    public function generatePhase2(string $phase1Content, string $keyword): array
    {
        $prompt = $this->buildPhase2Prompt($phase1Content, $keyword);
        $raw = $this->callAI($prompt);
        $questions = $this->parseQuestions($this->stripFences($raw));
        return $questions ?: $this->fallbackQuestions($keyword);
    }

    public function generatePhase3(string $phase1Content, array $questions, string $keyword, string $locale = 'id', string $tone = 'informative', array $lsiKeywords = [], array $entities = []): string
    {
        $plainText = strip_tags($phase1Content);
        $prompt = $this->buildPhase3Prompt($plainText, $questions, $keyword, $locale, $tone, $lsiKeywords, $entities);
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

    private function buildPhase1Prompt(string $keyword, string $locale, string $tone, array $lsiKeywords, array $entities, ?int $userId = null, string $memoryText = '', string $businessText = ''): string
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

        return <<<PROMPT
Anda adalah penulis konten profesional. Buat ARTIKEL LENGKAP seperti artikel di blog atau portal berita — BUKAN catatan, BUKAN poin-poin, BUKAN outline.

Target Keyword: {$keyword}
Bahasa: {$locale}
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
Minimal 1000 kata
Gunakan keyword utama secara natural 3-5 kali dalam artikel
Semua LSI keywords harus muncul minimal sekali
Semua entities harus tersemat dalam konteks relevan
Tulisan mengalir seperti artikel profesional — jangan kaku dan jangan seperti daftar
JANGAN gunakan catatan kaki atau komentar penulis
{$memoryText}
{$businessText}
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

    private function buildPhase3Prompt(string $phase1Text, array $questions, string $keyword, string $locale = 'id', string $tone = 'informative', array $lsiKeywords = [], array $entities = []): string
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
        $targetWords = max((int) ($wordCount * 2.0), 1200);

        $toneLabels = [
            'formal' => 'Formal dan profesional, cocok untuk artikel bisnis atau akademik',
            'informative' => 'Informatif dan edukatif, cocok untuk blog dan artikel panduan',
            'casual' => 'Santai dan ramah, cocok untuk blog personal',
            'persuasive' => 'Persuasif dan meyakinkan, cocok untuk konten marketing',
            'storytelling' => 'Bercerita dan naratif, cocok untuk konten engaging',
            'instructional' => 'Instruksional dan step-by-step, cocok untuk tutorial',
        ];

        $toneDesc = $toneLabels[$tone] ?? $tone;

        return <<<PROMPT
Anda adalah penulis konten profesional. Buat ARTIKEL LENGKAP seperti artikel di blog atau portal berita — BUKAN catatan, BUKAN poin-poin, BUKAN outline.

Target Keyword: {$keyword}
Bahasa: {$locale}
Nada/Tone: {$toneDesc}

KONTEN AWAL (gunakan sebagai fondasi, lalu KEMBANGKAN):
{$phase1Text}

PERTANYAAN KRITIS (jawab dan jadikan SUB-BAGIAN BARU dalam artikel, BUKAN format Q&A):
{$questionsText}

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

Output HANYA konten artikel dalam format Markdown, tanpa penjelasan tambahan di luar konten.
PROMPT;
    }

    public function generateMetaData(string $phase3Content, string $keyword, string $locale = 'id'): array
    {
        $plainText = strip_tags($phase3Content);
        $preview = mb_substr($plainText, 0, 3000);

        $prompt = <<<PROMPT
Anda adalah SEO specialist. Buat META TITLE dan META DESCRIPTION dengan fokus HIGH CTR dari konten berikut.

Keyword Target: {$keyword}
Bahasa: {$locale}

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
        $url = Setting::getValue('ai.9router.url', config('content-generator.providers.9router.url'));
        $apiKey = Setting::getValue('ai.9router.api_key', config('content-generator.providers.9router.api_key'));
        $model = Setting::getValue('ai.9router.chat_model', config('content-generator.providers.9router.model', 'openai/gpt-4o'));

        if (!$url) {
            throw new Exception('9Router URL is not configured.');
        }

        $delay = (int) $this->cfg('request_delay', 2);
        if ($delay > 0) {
            sleep($delay);
        }

        $systemPrompt = Setting::getValue('ai.system_prompt');
        if (!$systemPrompt) {
            $systemPrompt = 'Anda adalah asisten penulis konten profesional. Selalu gunakan format Markdown untuk struktur artikel.';
        }

        $response = Http::timeout(180)->withHeaders([
            'Authorization' => $apiKey ? "Bearer {$apiKey}" : '',
            'Content-Type' => 'application/json',
        ])->post("{$url}/v1/chat/completions", [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 16384,
            'stream' => false,
        ]);

        if ($response->failed()) {
            Log::error('ContentGenerator AI Failed', [
                'response' => $response->body(),
                'status' => $response->status(),
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
