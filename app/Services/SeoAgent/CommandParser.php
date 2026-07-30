<?php

namespace App\Services\SeoAgent;

class CommandParser
{
    public function parse(string $text): ?array
    {
        $text = trim($text);
        $lower = mb_strtolower($text);

        // Trend check
        if (preg_match('/^trend(?:ing)?\s+(.+)/i', $text, $m)) {
            return ['type' => 'TREND', 'keyword' => trim($m[1])];
        }
        if (preg_match('/^(?:bagaimana|gimana)\s+(?:trend|tren)\s+(.+)/i', $text, $m)) {
            return ['type' => 'TREND', 'keyword' => trim($m[1])];
        }

        // Research
        if (preg_match('/^(?:riset|research|teliti)\s+(.+)/i', $text, $m)) {
            return ['type' => 'RESEARCH', 'keyword' => trim($m[1])];
        }
        if (preg_match('/^tolong\s+(?:riset|research|teliti)\s+(.+)/i', $text, $m)) {
            return ['type' => 'RESEARCH', 'keyword' => trim($m[1])];
        }
        if (preg_match('/^coba\s+(?:riset|research)\s+(.+)/i', $text, $m)) {
            return ['type' => 'RESEARCH', 'keyword' => trim($m[1])];
        }

        // Generate content
        if (preg_match('/^(?:buat|bikin)\s+(?:konten|artikel)\s+(?:dari\s+)?(.+)/i', $text, $m)) {
            return ['type' => 'GENERATE_CONTENT', 'keyword' => trim($m[1])];
        }
        if (preg_match('/^konten(?:kan)?\s+(.+)/i', $text, $m)) {
            return ['type' => 'GENERATE_CONTENT', 'keyword' => trim($m[1])];
        }
        if (preg_match('/^generate\s+(?:konten|content|artikel)\s+(.+)/i', $text, $m)) {
            return ['type' => 'GENERATE_CONTENT', 'keyword' => trim($m[1])];
        }
        if (preg_match('/^tulis\s+(?:artikel|konten)\s+(.+)/i', $text, $m)) {
            return ['type' => 'GENERATE_CONTENT', 'keyword' => trim($m[1])];
        }

        // Check keyword
        if (preg_match('/^cek\s+(.+)/i', $text, $m)) {
            return ['type' => 'CHECK_KEYWORD', 'keyword' => trim($m[1])];
        }
        if (preg_match('/^periksa\s+(.+)/i', $text, $m)) {
            return ['type' => 'CHECK_KEYWORD', 'keyword' => trim($m[1])];
        }

        // Status
        if (preg_match('/^status\s+(\d+)/i', $text, $m)) {
            return ['type' => 'STATUS', 'id' => (int) $m[1]];
        }

        // Content length
        if (preg_match('/^panjang\s+(\d+)/i', $text, $m)) {
            return ['type' => 'CONTENT_LENGTH', 'id' => (int) $m[1]];
        }

        // Readability
        if (preg_match('/^readability\s+(\d+)/i', $text, $m)) {
            return ['type' => 'READABILITY', 'id' => (int) $m[1]];
        }
        if (preg_match('/^keterbacaan\s+(\d+)/i', $text, $m)) {
            return ['type' => 'READABILITY', 'id' => (int) $m[1]];
        }

        // Publish
        if (preg_match('/^publish\s+(\d+)/i', $text, $m)) {
            return ['type' => 'PUBLISH', 'id' => (int) $m[1]];
        }

        // Queue worker
        if (preg_match('/^(?:hidupkan|jalankan|start)\s+(?:worker|queue|antrian)/i', $text)) {
            return ['type' => 'QUEUE'];
        }
        if (preg_match('/^queue(?:\s+status)?$/i', $text)) {
            return ['type' => 'QUEUE'];
        }

        // Help
        if (preg_match('/^(?:bantuan|help|tolong|menu|perintah|command)/i', $text)) {
            return ['type' => 'HELP'];
        }

        return null;
    }
}
