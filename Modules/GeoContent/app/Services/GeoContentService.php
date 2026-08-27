<?php

namespace Modules\GeoContent\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;
use Modules\GeoContent\Models\GeoProject;
use Modules\GeoContent\Models\GeoContent;
use Modules\GeoContent\Models\GeoContentDiff;

class GeoContentService
{
    private ?CommonMarkConverter $markdown = null;
    public array $tokenUsage = ['tokens_in' => 0, 'tokens_out' => 0];

    protected function cfg(string $key, mixed $default = null): mixed
    {
        $db = Setting::getValue("geo-content.{$key}");
        if ($db !== null) return $db;
        return config("geo-content.{$key}", $default);
    }

    public function generateForProject(GeoProject $project): GeoContent
    {
        $keyword = $project->keyword_utama;
        $locale = $project->locale ?? 'id';

        // Ambil LSI/entities
        $lsiKeywords = $project->keywordResearch->lsi_keywords ?? [];
        $entities = $project->keywordResearch->entities ?? [];

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

        // Fakta ter-sintesis sanitized
        $factService = app(CompetitorFactService::class);
        $facts = $factService->getSynthesis($project);
        $facts = mb_substr($facts, 0, 8000);

        // Pertanyaan kritis
        $questionsText = '';
        foreach ($project->criticalFindings as $q) {
            $questionsText .= "- {$q->question}\n";
        }

        // Brand blocklist
        $brands = $project->competitor_brands ?? [];
        $brandRule = '';
        if (!empty($brands)) {
            $brandRule = "DILARANG KERAS menyebut brand kompetitor berikut (jangan muncul sama sekali): " . implode(', ', $brands) . ". Tulis ulang semua fakta dengan bahasa fresh milik brand klien.";
        }

        // Before snapshot untuk mode revisi
        $beforeSnapshot = $project->wp_post_before_snapshot ?? '';
        $beforeNote = '';
        if ($project->mode === 'revisi' && trim($beforeSnapshot) !== '') {
            $beforeNote = "KONTEN LAMA (sebagai pembanding, JANGAN copy verbatim, tingkatkan kualitasnya):\n" . mb_substr(strip_tags($beforeSnapshot), 0, 4000) . "\n\n";
        }

        $langRule = $locale === 'en'
            ? 'WRITE 100% IN ENGLISH.'
            : 'TULIS 100% DALAM BAHASA INDONESIA.';

        $prompt = <<<PROMPT
Anda adalah copywriter senior pakar SEO dan GEO. Buat ARTIKEL LENGKAP dengan framework AIDA.

{$langRule}

Target Keyword: {$keyword}
Framework: AIDA (Attention - hook pembuka yang menarik, Interest - bangun ketertarikan dengan fakta, Desire - ciptakan keinginan dengan benefit, Action - CTA ringan)

Keyword Utama: {$keyword} (gunakan natural 3-5x)
LSI Keywords (wajib natural, minimal 1x masing-masing):
{$lsiText}
Entities (sematkan natural):
{$entityText}

FAKTA TERSINTESIS DARI KOMPETITOR (gabungan multi-sumber, sudah tanpa brand — JANGAN copy kalimat verbatim, sintesis ulang dengan sudut fresh):
{$facts}

PERTANYAAN KRITIS (jadikan sub-bagian ##, BUKAN format Q&A):
{$questionsText}

{$brandRule}

{$beforeNote}
ATURAN PENULISAN:
- Satu # judul utama mengandung keyword
- 3-5 sub-bagian ## yang menjawab pertanyaan kritis
- Setiap paragraf minimal 3 kalimat, mengalir dengan konjungsi
- Variasikan panjang kalimat (5-25 kata), pakai kata transisi
- AIDA: pembuka Attention yang hook, tengah Interest/Desire dengan fakta+benefit, akhir Action
- Minimal 1200 kata, maksimal informatif
- Jangan sebut brand kompetitor sama sekali
- Jangan copy kalimat sumber verbatim — sintesis fresh
- Tulis seperti manusia profesional, hindari AI-slop

Output HANYA Markdown artikel, tanpa penjelasan di luar konten.
PROMPT;

        $raw = $this->callAI($prompt);
        $html = $this->processContent($raw);

        // Brand scrub post-filter
        $scrubber = app(BrandScrubberService::class);
        if (!empty($brands) && config('geo-content.brand_scrub.enabled', true)) {
            $html = $scrubber->scrub($html, $brands);
        }

        // NoAiSlop scan
        try {
            $slop = app(\Modules\ContentGenerator\Services\NoAiSlopService::class);
            $hits = $slop->scan($html, $locale);
            if ($slop->shouldRewrite($hits, $locale)) {
                Log::warning('GeoContent: slop detected', ['hits' => count($hits)]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $wordCount = str_word_count(strip_tags($html));

        $content = GeoContent::create([
            'geo_project_id' => $project->id,
            'before_snapshot' => $beforeSnapshot ?: null,
            'final_content' => $html,
            'word_count' => $wordCount,
            'tokens_in' => $this->tokenUsage['tokens_in'],
            'tokens_out' => $this->tokenUsage['tokens_out'],
            'status' => 'completed',
        ]);

        // Generate diff untuk mode revisi
        if ($project->mode === 'revisi' && trim($beforeSnapshot) !== '') {
            $this->generateDiff($project, $beforeSnapshot, $html);
        }

        // Meta
        try {
            $meta = $this->generateMeta($html, $keyword, $locale);
            $content->update(['meta_title' => $meta['title'], 'meta_description' => $meta['description']]);
        } catch (\Throwable $e) {
            Log::warning('GeoContent: meta gagal', ['error' => $e->getMessage()]);
        }

        return $content;
    }

    protected function generateDiff(GeoProject $project, string $before, string $after): void
    {
        $beforePlain = strip_tags($before);
        $afterPlain = strip_tags($after);
        $beforeHash = hash('sha256', $beforePlain);
        $afterHash = hash('sha256', $afterPlain);

        // Simple inline diff: highlight additions
        $inline = $this->simpleInlineDiff($beforePlain, $afterPlain);
        $sideBySide = '<div class="row"><div class="col"><h5>Before</h5><div>' . e($beforePlain) . '</div></div><div class="col"><h5>After</h5><div>' . e($afterPlain) . '</div></div></div>';

        // Try jfcherng/php-diff if available
        if (class_exists(\Jfcherng\Diff\DiffHelper::class)) {
            try {
                $inline = \Jfcherng\Diff\DiffHelper::calculate($beforePlain, $afterPlain, 'Inline', ['detailLevel' => 'word']);
            } catch (\Throwable $e) {
            }
        }

        GeoContentDiff::create([
            'geo_project_id' => $project->id,
            'before_hash' => $beforeHash,
            'after_hash' => $afterHash,
            'inline_diff_html' => $inline,
            'side_by_side_html' => $sideBySide,
        ]);
    }

    protected function simpleInlineDiff(string $before, string $after): string
    {
        $beforeWords = preg_split('/\s+/', $before);
        $afterWords = preg_split('/\s+/', $after);
        // Very simple: show after with del/ins for demo
        return '<del>' . e(mb_substr($before, 0, 500)) . '</del> <ins>' . e(mb_substr($after, 0, 800)) . '</ins>';
    }

    protected function generateMeta(string $content, string $keyword, string $locale): array
    {
        $preview = mb_substr(strip_tags($content), 0, 3000);
        $prompt = <<<PROMPT
Buat META TITLE (max 60 char, mengandung keyword di awal, high CTR) dan META DESCRIPTION (150-160 char, mengandung keyword 1-2x, akhiri micro-CTA) untuk konten berikut.

Keyword: {$keyword}
Konten: {$preview}

Return ONLY JSON: {"title": "...", "description": "..."}
PROMPT;
        $raw = $this->callAI($prompt);
        $clean = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)));
        $decoded = json_decode($clean, true);
        if ($decoded && isset($decoded['title'])) {
            return ['title' => mb_substr(trim($decoded['title']), 0, 60), 'description' => mb_substr(trim($decoded['description']), 0, 160)];
        }
        return ['title' => mb_substr($keyword, 0, 60), 'description' => mb_substr($preview, 0, 160)];
    }

    private function processContent(string $raw): string
    {
        $content = trim(preg_replace('/^```(?:markdown|html|md|json)?\s*\n?|\n?\s*```$/i', '', trim($raw)));
        if (!$this->markdown) {
            $this->markdown = new CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        }
        $html = $this->markdown->convert($content)->getContent();
        return strip_tags($html, ['h1','h2','h3','h4','p','br','hr','ul','ol','li','a','strong','em','b','i','blockquote','pre','code','table','thead','tbody','tr','th','td','img','figure','figcaption']);
    }

    private function callAI(string $prompt): string
    {
        $ai = Setting::aiConfig();
        $url = $ai['url'];
        $apiKey = $ai['api_key'];
        $model = $ai['chat_model'];
        if (!$url) throw new \Exception('AI URL belum dikonfigurasi.');
        $delay = (int) $this->cfg('ai.request_delay', 2);
        if ($delay > 0) sleep($delay);
        $endpoint = str_ends_with(rtrim($url, '/'), '/v1') ? rtrim($url, '/') . '/chat/completions' : rtrim($url, '/') . '/v1/chat/completions';
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Anda adalah copywriter senior. Output Markdown.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 16384,
            'stream' => false,
        ];
        $response = Http::timeout(300)->withHeaders([
            'Authorization' => $apiKey ? "Bearer {$apiKey}" : '',
            'Content-Type' => 'application/json',
        ])->post($endpoint, $payload);
        if (!$response->successful()) {
            $body = $response->body();
            if (str_contains($body, 'MONTHLY_REQUEST_COUNT') || $response->status() === 402) {
                throw new \Exception('AI quota habis (MONTHLY_REQUEST_COUNT) — ganti model di Settings/AI atau tunggu reset. ' . substr($body, 0, 200));
            }
            throw new \Exception('AI HTTP ' . $response->status() . ': ' . substr($body, 0, 300));
        }
        $data = $response->json();
        $usage = $data['usage'] ?? [];
        $this->tokenUsage['tokens_in'] += $usage['prompt_tokens'] ?? 0;
        $this->tokenUsage['tokens_out'] += $usage['completion_tokens'] ?? 0;
        return $data['choices'][0]['message']['content'] ?? '';
    }
}
