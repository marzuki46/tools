<?php

namespace App\Support;

class SeoText
{
    private const STOP_WORDS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'pada', 'dengan', 'adalah', 'itu',
        'ini', 'agar', 'akan', 'sudah', 'telah', 'para', 'atau', 'serta', 'dalam', 'oleh',
        'sebagai', 'juga', 'bisa', 'dapat', 'agar', 'supaya', 'ketika', 'saat', 'the',
        'a', 'an', 'of', 'to', 'for', 'in', 'on', 'with', 'is', 'are', 'and', 'or',
        'your', 'you', 'that', 'this', 'it', 'as', 'at', 'by', 'from', 'be',
    ];

    public static function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $words = preg_split('/[\s_-]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_filter($words, fn ($w) => !in_array($w, self::STOP_WORDS, true)));
        $words = array_slice($words, 0, 5);

        $slug = '';
        foreach ($words as $word) {
            $candidate = $slug === '' ? $word : "{$slug}-{$word}";
            if (strlen($candidate) > 60) {
                break;
            }
            $slug = $candidate;
        }

        return $slug ?: 'artikel-' . now()->timestamp;
    }

    public static function capTitle(string $text, int $max = 60, string $keyword = ''): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return $text;
        }
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $trimmed = self::cutAtWordBoundary($text, $max);
        $keyword = trim($keyword);

        if ($keyword !== '' && mb_stripos($trimmed, $keyword) === false) {
            $keywordPart = mb_strlen($keyword) <= $max ? $keyword : self::cutAtWordBoundary($keyword, $max);
            $remaining = $max - mb_strlen($keywordPart) - 3;
            if ($remaining >= 10) {
                $head = self::cutAtWordBoundary($text, $remaining);
                return "{$keywordPart} — {$head}";
            }
            return $keywordPart;
        }

        return $trimmed;
    }

    public static function capDescription(string $text, int $max = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return self::cutAtWordBoundary($text, $max);
    }

    private static function cutAtWordBoundary(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max + 1);
        $spacePos = mb_strrpos($cut, ' ');
        if ($spacePos === false || $spacePos < intval($max * 0.5)) {
            $spacePos = $max;
        }

        return rtrim(mb_substr($text, 0, $spacePos), " \t\n\r\0\x0B,.;:-");
    }
}
