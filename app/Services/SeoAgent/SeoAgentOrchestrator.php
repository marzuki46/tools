<?php

namespace App\Services\SeoAgent;

use App\Models\SeoAgentLog;
use App\Models\Setting;
use App\Services\FonnteService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\KeywordResearch\Models\KeywordResearch;

class SeoAgentOrchestrator
{
    public function __construct(
        protected FonnteService $fonnte,
        protected CommandParser $parser,
        protected GoogleTrendsService $trends,
        protected ContentQualityChecker $quality,
    ) {}

    public function handle(string $sender, string $message, string $name = ''): array
    {
        $parsed = $this->parser->parse($message);

        $log = SeoAgentLog::create([
            'sender' => $sender,
            'sender_name' => $name,
            'message' => $message,
            'command_type' => $parsed['type'] ?? 'UNKNOWN',
            'command_data' => $parsed,
            'status' => 'processing',
        ]);

        try {
            if (!$parsed) {
                $reply = $this->unknownCommand();
            } else {
                $reply = match ($parsed['type']) {
                    'TREND' => $this->handleTrend($parsed, $sender),
                    'RESEARCH' => $this->handleResearch($parsed, $sender, $log),
                    'GENERATE_CONTENT' => $this->handleGenerateContent($parsed, $sender, $log),
                    'CHECK_KEYWORD' => $this->handleCheckKeyword($parsed),
                    'STATUS' => $this->handleStatus($parsed),
                    'CONTENT_LENGTH' => $this->handleContentLength($parsed),
                    'READABILITY' => $this->handleReadability($parsed),
                    'PUBLISH' => $this->handlePublish($parsed),
                    'QUEUE' => $this->handleQueue(),
                    'HELP' => $this->helpMessage(),
                    default => $this->unknownCommand(),
                };
            }

            $log->update([
                'response' => $reply,
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            $sendResult = $this->fonnte->send($sender, $reply);
            if (!$sendResult['success']) {
                Log::warning('SeoAgent: failed to send reply', [
                    'sender' => $sender,
                    'error' => $sendResult['message'],
                ]);
            }

            return ['success' => true, 'reply' => $reply];
        } catch (Exception $e) {
            Log::error('SeoAgent: orchestrator error', [
                'sender' => $sender,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            $errorReply = "Maaf, terjadi kesalahan: " . $e->getMessage();
            $this->fonnte->send($sender, $errorReply);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function handleTrend(array $cmd, string $sender): string
    {
        $keyword = $cmd['keyword'];
        $analysis = $this->trends->analyze($keyword);

        $lines = [];
        $lines[] = "📊 *TREND: {$keyword}*";
        $lines[] = "";
        $lines[] = "Arah: {$analysis['trend_direction']} (skor: {$analysis['trend_score']}/100)";
        $lines[] = "";
        $lines[] = $analysis['summary'];

        if (!empty($analysis['related_topics'])) {
            $lines[] = "";
            $lines[] = "Topik terkait:";
            foreach (array_slice($analysis['related_topics'], 0, 5) as $t) {
                $lines[] = "• {$t}";
            }
        }

        if (!empty($analysis['related_questions'])) {
            $lines[] = "";
            $lines[] = "Pertanyaan terkait:";
            foreach (array_slice($analysis['related_questions'], 0, 3) as $q) {
                $lines[] = "• {$q}";
            }
        }

        return implode("\n", $lines);
    }

    protected function handleResearch(array $cmd, string $sender, SeoAgentLog $log): string
    {
        $keyword = $cmd['keyword'];

        $existing = KeywordResearch::where('target_keyword', $keyword)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($existing) {
            $lsiCount = count($existing->lsi_keywords ?? []);
            $entityCount = count($existing->entities ?? []);
            return "✅ *Keyword sudah pernah diriset!*\n\nKeyword: {$existing->target_keyword}\nLSI: {$lsiCount} kata kunci\nEntities: {$entityCount} entitas\nID Riset: {$existing->id}\n\nGunakan `konten {$keyword}` untuk buat artikel.";
        }

        // Check if already processing
        $processing = KeywordResearch::where('target_keyword', $keyword)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($processing) {
            return "⏳ Riset untuk '{$keyword}' sedang diproses. ID: {$processing->id}. Cek nanti dengan `status {$processing->id}`.";
        }

        // Dispatch job
        $research = KeywordResearch::create([
            'user_id' => Setting::getValue('seo-agent.default_user_id', 1),
            'target_keyword' => $keyword,
            'locale' => 'id',
            'lsi_count' => 12,
            'entities_count' => 7,
            'status' => 'pending',
            'source' => 'seo-agent',
        ]);

        dispatch(new \App\Jobs\SeoAgentProcessKeywordJob($research, $sender, $log->id));

        return "⏳ Riset untuk '{$keyword}' sedang diantrikan. ID: {$research->id}.\nKami akan kirim hasilnya otomatis ke WA ini ya.";
    }

    protected function handleGenerateContent(array $cmd, string $sender, SeoAgentLog $log): string
    {
        $keyword = $cmd['keyword'];

        // Check existing content
        $existing = ContentGeneration::where('target_keyword', $keyword)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($existing) {
            $preview = mb_substr(strip_tags($existing->phase_3_content ?? ''), 0, 200);
            $lines = [];
            $lines[] = "✅ *Konten untuk '{$keyword}' sudah pernah dibuat!*";
            $lines[] = "";
            $lines[] = "ID: {$existing->id}";
            $lines[] = "Preview: {$preview}...";
            $lines[] = "";
            $lines[] = "Gunakan `panjang {$existing->id}` untuk cek panjang konten.";
            $lines[] = "Gunakan `readability {$existing->id}` untuk cek readability.";
            $lines[] = "Gunakan `publish {$existing->id}` untuk publish.";
            return implode("\n", $lines);
        }

        // Check processing
        $processing = ContentGeneration::where('target_keyword', $keyword)
            ->whereIn('status', ['draft', 'processing'])
            ->latest()
            ->first();

        if ($processing) {
            return "⏳ Konten untuk '{$keyword}' sedang diproses (Phase {$processing->current_phase}). Cek nanti.";
        }

        // Check if research exists
        $research = KeywordResearch::where('target_keyword', $keyword)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if (!$research) {
            // Need to research first
            $research = KeywordResearch::create([
                'user_id' => Setting::getValue('seo-agent.default_user_id', 1),
                'target_keyword' => $keyword,
                'locale' => 'id',
                'lsi_count' => 12,
                'entities_count' => 7,
                'status' => 'pending',
                'source' => 'seo-agent',
            ]);

            dispatch(new \App\Jobs\SeoAgentProcessKeywordJob($research, $sender, $log->id, true));

            return "⏳ Riset keyword '{$keyword}' dulu ya. Proses... Nanti lanjut bikin konten setelah riset selesai. Kami kabari lewat WA.";
        }

        // Create and dispatch content generation
        $generation = ContentGeneration::create([
            'user_id' => Setting::getValue('seo-agent.default_user_id', 1),
            'target_keyword' => $keyword,
            'locale' => 'id',
            'tone' => 'informative',
            'lsi_keywords' => $research->lsi_keywords ?? [],
            'entities' => $research->entities ?? [],
            'keyword_research_id' => $research->id,
            'status' => 'draft',
            'current_phase' => 0,
        ]);

        dispatch(new \App\Jobs\SeoAgentProcessContentJob($generation, $sender, $log->id));

        return "⏳ Konten untuk '{$keyword}' sedang dibuat. ID: {$generation->id}.\nKami kirim hasilnya otomatis ke WA ini ya.";
    }

    protected function handleCheckKeyword(array $cmd): string
    {
        $keyword = $cmd['keyword'];

        $research = KeywordResearch::where('target_keyword', $keyword)
            ->latest()
            ->first();

        $content = ContentGeneration::where('target_keyword', $keyword)
            ->latest()
            ->first();

        $lines = [];
        $lines[] = "🔍 *CEK KEYWORD: {$keyword}*";
        $lines[] = "";

        if ($research) {
            $statusLabel = $research->status === 'completed' ? '✅ Selesai' : ($research->status === 'pending' ? '⏳ Diproses' : '❌ Gagal');
            $lsiCount = count($research->lsi_keywords ?? []);
            $lines[] = "Riset: {$statusLabel}";
            $lines[] = "LSI Keywords: {$lsiCount}";
            $lines[] = "ID Riset: {$research->id}";
        } else {
            $lines[] = "Riset: ❌ Belum pernah diriset";
        }

        $lines[] = "";

        if ($content) {
            $url = $content->published_url ?? '-';
            $statusLabel = $content->status === 'completed' ? '✅ Selesai' : ($content->status === 'draft' ? '⏳ Diproses' : '❌ Gagal');
            $lines[] = "Konten: {$statusLabel}";
            $lines[] = "ID Konten: {$content->id}";
            if ($url && $url !== '-') {
                $lines[] = "URL: {$url}";
            }
        } else {
            $lines[] = "Konten: ❌ Belum dibuat";
            $lines[] = "";
            $lines[] = "Gunakan `konten {$keyword}` untuk buat artikel.";
        }

        return implode("\n", $lines);
    }

    protected function handleStatus(array $cmd): string
    {
        $id = $cmd['id'];

        $generation = ContentGeneration::find($id);
        if (!$generation) {
            return "❌ Konten dengan ID {$id} tidak ditemukan.";
        }

        $phaseLabels = [
            0 => 'Belum dimulai',
            1 => '✅ Phase 1 (Draft awal)',
            2 => '✅ Phase 2 (Pertanyaan kritis)',
            3 => '✅ Phase 3 (Konten final)',
        ];

        $statusLabel = match ($generation->status) {
            'draft' => '⏳ Diproses',
            'completed' => '✅ Selesai',
            'failed' => '❌ Gagal',
            default => $generation->status,
        };

        $lines = [];
        $lines[] = "📋 *STATUS KONTEN ID: {$id}*";
        $lines[] = "";
        $lines[] = "Keyword: {$generation->target_keyword}";
        $lines[] = "Status: {$statusLabel}";
        $lines[] = "Phase: {$phaseLabels[$generation->current_phase] ?? $generation->current_phase}";

        if ($generation->phase_3_content) {
            $wordCount = str_word_count(strip_tags($generation->phase_3_content));
            $lines[] = "Panjang: {$wordCount} kata";
        }

        if ($generation->published_url) {
            $lines[] = "URL: {$generation->published_url}";
        }

        return implode("\n", $lines);
    }

    protected function handleContentLength(array $cmd): string
    {
        $id = $cmd['id'];
        $generation = ContentGeneration::find($id);

        if (!$generation) {
            return "❌ Konten dengan ID {$id} tidak ditemukan.";
        }

        $content = $generation->phase_3_content ?? $generation->phase_1_content ?? '';
        if (empty($content)) {
            return "❌ Konten ID {$id} belum memiliki konten.";
        }

        $stats = $this->quality->checkLength($content);

        $lines = [];
        $lines[] = "📏 *PANJANG KONTEN ID: {$id}*";
        $lines[] = "";
        $lines[] = "Karakter: {$stats['characters']}";
        $lines[] = "Kata: {$stats['words']} ({$stats['word_range']})";
        $lines[] = "Kalimat: {$stats['sentences']}";
        $lines[] = "Paragraf: {$stats['paragraphs']}";
        $lines[] = "Rata-rata kata/kalimat: {$stats['avg_words_per_sentence']}";
        $lines[] = "Estimasi baca: {$stats['reading_time_minutes']} menit";

        if ($stats['short_paragraphs'] > 0) {
            $lines[] = "";
            $lines[] = "⚠️ {$stats['short_paragraphs']} paragraf terlalu pendek. Usahakan min 20 kata per paragraf.";
        }

        $wordCount = $stats['words'];
        $seoAdvice = $wordCount < 500
            ? "Konten terlalu pendek untuk SEO (min 1000 kata disarankan)."
            : ($wordCount < 1000
                ? "Konten cukup, tapi ideal SEO 1000+ kata."
                : "✅ Panjang konten sudah ideal untuk SEO.");
        $lines[] = "";
        $lines[] = $seoAdvice;

        return implode("\n", $lines);
    }

    protected function handleReadability(array $cmd): string
    {
        $id = $cmd['id'];
        $generation = ContentGeneration::find($id);

        if (!$generation) {
            return "❌ Konten dengan ID {$id} tidak ditemukan.";
        }

        $content = $generation->phase_3_content ?? $generation->phase_1_content ?? '';
        if (empty($content)) {
            return "❌ Konten ID {$id} belum memiliki konten.";
        }

        $result = $this->quality->checkReadability($content);

        $scoreEmoji = $result['score'] >= 70 ? '🟢' : ($result['score'] >= 50 ? '🟡' : '🔴');

        $lines = [];
        $lines[] = "📖 *READABILITY ID: {$id}*";
        $lines[] = "";
        $lines[] = "{$scoreEmoji} Skor: {$result['score']}/100";
        $lines[] = "Tingkat: {$result['level']}";
        $lines[] = "";
        $lines[] = "Rata-rata suku kata/kata: {$result['avg_syllables_per_word']}";
        $lines[] = "Rata-rata kata/kalimat: {$result['avg_words_per_sentence']}";
        $lines[] = "Kata sulit: {$result['complex_word_ratio']}%";

        if (!empty($result['issues'])) {
            $lines[] = "";
            $lines[] = "⚠️ *Masalah ditemukan:*";
            foreach ($result['issues'] as $issue) {
                $lines[] = "• {$issue}";
            }
            $lines[] = "";
            $lines[] = "💡 *Saran:* Gunakan kalimat lebih pendek, kurangi kata sulit, perbanyak paragraf.";
        } else {
            $lines[] = "";
            $lines[] = "✅ Tidak ada masalah readability.";
        }

        return implode("\n", $lines);
    }

    protected function handlePublish(array $cmd): string
    {
        $id = $cmd['id'];
        $generation = ContentGeneration::find($id);

        if (!$generation) {
            return "❌ Konten dengan ID {$id} tidak ditemukan.";
        }

        if (empty($generation->phase_3_content)) {
            return "❌ Konten ID {$id} belum selesai (Phase 3 belum ada). Cek dengan `status {$id}`.";
        }

        return "📤 *PUBLISH KONTEN ID: {$id}*\n\nFitur publish ke WordPress dalam pengembangan.\n\nUntuk sementara, silakan copy-paste konten secara manual ke WordPress.\n\nKonten tersedia di: https://tools.juki.eu.org/content-generator/{$id}";
    }

    protected function handleQueue(): string
    {
        $pendingJobs = \DB::table('jobs')->count();
        $failedJobs = \DB::table('failed_jobs')->count();

        $lines = [];
        $lines[] = "⚙️ *QUEUE STATUS*";
        $lines[] = "";
        $lines[] = "Antrian pending: {$pendingJobs}";
        $lines[] = "Antrian gagal: {$failedJobs}";

        if ($pendingJobs === 0) {
            $lines[] = "";
            $lines[] = "✅ Tidak ada antrian. Worker standby.";
            return implode("\n", $lines);
        }

        $basePath = base_path();
        $lines[] = "";
        $lines[] = "💡 Jalankan worker manual via SSH:";
        $lines[] = "`cd {$basePath}; ea-php84 artisan queue:work --timeout=240 --tries=3`";
        $lines[] = "";
        $lines[] = "Atau pastikan cron sudah jalan:";
        $lines[] = "`* * * * * cd {$basePath}; ea-php84 artisan schedule:run >> /dev/null 2>&1`";

        if (function_exists('exec')) {
            try {
                $cmd = 'cd ' . escapeshellarg($basePath) . ' && nohup ea-php84 artisan queue:work --stop-when-empty --timeout=240 --tries=3 --queue=default,keyword-research,content-generator > /dev/null 2>&1 &';
                exec($cmd, $output, $exitCode);
                if ($exitCode === 0) {
                    $lines[] = "";
                    $lines[] = "🚀 Worker dijalankan! Memproses {$pendingJobs} antrian...";
                } else {
                    $lines[] = "";
                    $lines[] = "⚠️ Gagal menjalankan worker otomatis (exit code {$exitCode}).";
                }
            } catch (\Throwable $e) {
                $lines[] = "";
                $lines[] = "⚠️ Tidak bisa jalankan worker dari sini. Jalankan via SSH ya.";
            }
        } else {
            $lines[] = "";
            $lines[] = "⚠️ `exec()` tidak aktif di server ini. Jalankan worker via SSH.";
        }

        return implode("\n", $lines);
    }

    protected function helpMessage(): string
    {
        return "🤖 *SEO AGENT — BANTUAN*\n\nBerikut perintah yang didukung:\n\n"
            . "📊 *Trend*\n`trend <keyword>` — Cek tren keyword\n\n"
            . "🔍 *Riset*\n`riset <keyword>` — Riset LSI & entities\n\n"
            . "✍️ *Konten*\n`konten <keyword>` — Buat artikel SEO\n\n"
            . "✅ *Cek*\n`cek <keyword>` — Cek status keyword\n`status <id>` — Cek progress konten\n\n"
            . "📏 *Kualitas*\n`panjang <id>` — Cek panjang konten\n`readability <id>` — Cek readability\n\n"
            . "📤 *Publish*\n`publish <id>` — Publish ke WordPress\n\n"
            . "⚙️ *Worker*\n`queue` / `hidupkan worker` — Cek & jalankan antrian\n\n"
            . "💡 *Tips:*\n• Semua perintah bisa pakai bahasa Indonesia\n• Hasil riset & konten dikirim otomatis ke WA";
    }

    protected function unknownCommand(): string
    {
        return "Maaf, perintah tidak dikenali. Ketik *bantuan* untuk daftar perintah.";
    }
}
