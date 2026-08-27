<?php

namespace Modules\GeoContent\Services;

class BrandScrubberService
{
    /**
     * Extract brand candidates from URL + HTML metadata.
     */
    public function extractBrands(string $url, string $html = ''): array
    {
        $brands = [];
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            $host = preg_replace('/^www\./', '', $host);
            $brands[] = $host;
            $brands[] = explode('.', $host)[0] ?? '';
        }

        if ($html !== '') {
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
                $title = trim(strip_tags($m[1]));
                // Take first 3 words as potential brand
                $words = preg_split('/\s+/', $title);
                if (!empty($words[0])) {
                    $brands[] = $words[0];
                }
            }
            if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
                $brands[] = trim($m[1]);
            }
        }

        $brands = array_filter(array_unique(array_map('trim', $brands)));
        // Filter very short or generic
        $brands = array_filter($brands, fn ($b) => mb_strlen($b) >= 3);

        return array_values($brands);
    }

    /**
     * Remove brand mentions from text.
     * Strategy: replace brand sentence with generic, or remove brand word.
     */
    public function scrub(string $text, array $brands): string
    {
        if (empty($brands) || trim($text) === '') {
            return $text;
        }

        foreach ($brands as $brand) {
            $escaped = preg_quote($brand, '/');
            // Remove standalone brand mentions (case-insensitive, word boundary)
            $text = preg_replace('/\b' . $escaped . '\b/iu', '', $text);
        }

        // Clean up double spaces and empty sentences
        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = preg_replace('/\.\s*\./', '.', $text);

        return trim($text);
    }

    /**
     * Check if text still contains any brand.
     */
    public function containsBrand(string $text, array $brands): bool
    {
        foreach ($brands as $brand) {
            if (preg_match('/\b' . preg_quote($brand, '/') . '\b/iu', $text)) {
                return true;
            }
        }
        return false;
    }
}
