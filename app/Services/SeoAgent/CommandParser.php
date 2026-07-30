<?php

namespace App\Services\SeoAgent;

class CommandParser
{
    protected function cleanKeyword(string $kw): string
    {
        $kw = trim($kw);
        $kw = preg_replace('/[〈〈《]/u', '<', $kw);
        $kw = preg_replace('/[〉〉》]/u', '>', $kw);
        $kw = preg_replace('/<[^>]+>/', '', $kw);
        $kw = preg_replace('/\s*[—–-]\s*(?:Cek|cek|Lihat|lihat).*$/u', '', $kw);
        $kw = preg_replace('/\s+di\s+indonesia$/i', '', $kw);
        return trim($kw);
    }

    public function parse(string $text): ?array
    {
        $text = trim($text);
        $lower = mb_strtolower($text);

        // ── Help (harus paling awal biar gak ketiban pattern lain) ──
        if (preg_match('/^(?:bantuan|help|tolong|menu|perintah|command|\/start|\/help)/i', $text)) {
            return ['type' => 'HELP'];
        }

        // ── Trend ──
        if (preg_match('/(?:trend(?:ing)?|tren)\s+(.+)/i', $text, $m)) {
            return ['type' => 'TREND', 'keyword' => $this->cleanKeyword($m[1])];
        }
        if (preg_match('/^coba\s+(?:kmu|kamu|lo|lu)?\s*cek\s+(?:trend|tren)\s+(.+)/i', $text, $m)) {
            return ['type' => 'TREND', 'keyword' => $this->cleanKeyword($m[1])];
        }

        // ── Research ──
        if (preg_match('/(?:riset|research|teliti)\s+(.+)/i', $text, $m)) {
            return ['type' => 'RESEARCH', 'keyword' => $this->cleanKeyword($m[1])];
        }

        // ── Generate content ──
        if (preg_match('/^(?:buat|bikin)\s+(?:konten|artikel)\s+(?:dari\s+)?(.+)/i', $text, $m)) {
            return ['type' => 'GENERATE_CONTENT', 'keyword' => $this->cleanKeyword($m[1])];
        }
        if (preg_match('/^konten(?:kan)?\s+(.+)/i', $text, $m)) {
            return ['type' => 'GENERATE_CONTENT', 'keyword' => $this->cleanKeyword($m[1])];
        }
        if (preg_match('/^generate\s+(?:konten|content|artikel)\s+(.+)/i', $text, $m)) {
            return ['type' => 'GENERATE_CONTENT', 'keyword' => $this->cleanKeyword($m[1])];
        }
        if (preg_match('/^tulis\s+(?:artikel|konten)\s+(.+)/i', $text, $m)) {
            return ['type' => 'GENERATE_CONTENT', 'keyword' => $this->cleanKeyword($m[1])];
        }
        if (preg_match('/^coba\s+(?:kmu|kamu|lo|lu)?\s*buat(?:kan)?\s+(?:konten|artikel)\s+(.+)/i', $text, $m)) {
            return ['type' => 'GENERATE_CONTENT', 'keyword' => $this->cleanKeyword($m[1])];
        }

        // ── Check keyword ──
        if (preg_match('/(?:cek|periksa)\s+(?:keyword\s+)?(.+)/i', $text, $m)) {
            return ['type' => 'CHECK_KEYWORD', 'keyword' => $this->cleanKeyword($m[1])];
        }

        // ── Status ──
        if (preg_match('/^status\s+(\d+)/i', $text, $m)) {
            return ['type' => 'STATUS', 'id' => (int) $m[1]];
        }

        // ── Content length ──
        if (preg_match('/^panjang\s+(\d+)/i', $text, $m)) {
            return ['type' => 'CONTENT_LENGTH', 'id' => (int) $m[1]];
        }

        // ── Readability ──
        if (preg_match('/^readability\s+(\d+)/i', $text, $m)) {
            return ['type' => 'READABILITY', 'id' => (int) $m[1]];
        }
        if (preg_match('/^keterbacaan\s+(\d+)/i', $text, $m)) {
            return ['type' => 'READABILITY', 'id' => (int) $m[1]];
        }

        // ── Publish ──
        if (preg_match('/^publish\s+(\d+)/i', $text, $m)) {
            return ['type' => 'PUBLISH', 'id' => (int) $m[1]];
        }

        // ── Queue worker ──
        if (preg_match('/(?:hidupkan|jalankan|start)\s+(?:worker|queue|antrian)/i', $text)) {
            return ['type' => 'QUEUE'];
        }
        if (preg_match('/^queue(?:\s+status)?$/i', $text)) {
            return ['type' => 'QUEUE'];
        }
        if (preg_match('/(?:matikan|stop|nonaktifkan)\s+(?:worker|queue|antrian)/i', $text)) {
            return ['type' => 'STOP_QUEUE'];
        }

        return null;
    }
}
