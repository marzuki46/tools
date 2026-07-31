<?php

namespace Modules\AgentConnector\Services;

class ContentAnalyzerService
{
    public function analyze(string $content, string $keyword): array
    {
        $plainText = strip_tags($content);
        $wordCount = $this->countWords($plainText);
        $sentences = $this->countSentences($plainText);
        $paragraphs = $this->countParagraphs($content);
        $headings = $this->countHeadings($content);
        $images = $this->countImages($content);
        $links = $this->countLinks($content);
        $keywordDensity = $this->keywordDensity($plainText, $keyword);
        $readabilityScore = $this->readabilityScore($plainText);
        $passiveVoice = $this->estimatePassiveVoice($plainText);
        $avgWordsPerSentence = $sentences > 0 ? round($wordCount / $sentences, 1) : 0;

        $seoScore = $this->calculateSeoScore($keywordDensity, $wordCount, $headings, $links, $keyword);
        $structureScore = $this->calculateStructureScore($wordCount, $avgWordsPerSentence, $paragraphs, $headings, $sentences, $paragraphs > 0 ? round($sentences / $paragraphs, 1) : 0);
        $readabilityScoreValue = $this->calculateReadabilityScore($readabilityScore, $passiveVoice);
        $imageScore = $this->calculateImageScore($images);

        $totalScore = (int) round(
            $seoScore * 0.3 +
            $structureScore * 0.25 +
            $readabilityScoreValue * 0.25 +
            $imageScore * 0.2
        );

        $issues = $this->findIssues($keywordDensity, $wordCount, $headings, $links, $avgWordsPerSentence, $readabilityScore, $passiveVoice, $images, $paragraphs, $sentences > 0 && $paragraphs > 0 ? round($sentences / $paragraphs, 1) : 0, $keyword);

        return [
            'total_score' => $totalScore,
            'seo_score' => $seoScore,
            'structure_score' => $structureScore,
            'readability_score' => $readabilityScoreValue,
            'image_score' => $imageScore,
            'details' => [
                'keyword' => $keyword,
                'keyword_density' => $keywordDensity,
                'total_words' => $wordCount,
                'total_sentences' => $sentences,
                'total_paragraphs' => $paragraphs,
                'total_headings' => $headings['total'],
                'heading_order' => $headings['order'],
                'avg_words_per_sentence' => $avgWordsPerSentence,
                'avg_sentences_per_paragraph' => $paragraphs > 0 ? round($sentences / max(1, $paragraphs), 1) : 0,
                'total_images' => $images['total'],
                'images_with_alt' => $images['with_alt'],
                'images_webp' => $images['webp'],
                'internal_links' => $links['internal'],
                'external_links' => $links['external'],
                'readability_score' => $readabilityScore,
                'complex_word_ratio' => $this->estimateComplexWordRatio($plainText),
                'passive_voice_percent' => $passiveVoice,
            ],
            'issues' => $issues,
        ];
    }

    private function countWords(string $text): int
    {
        return str_word_count($text);
    }

    private function countSentences(string $text): int
    {
        preg_match_all('/[.!?]+/', $text, $matches);
        return max(1, count($matches[0]));
    }

    private function countParagraphs(string $html): int
    {
        preg_match_all('/<p[^>]*>/i', $html, $matches);
        return max(1, count($matches[0]));
    }

    private function countHeadings(string $html): array
    {
        $order = [];
        foreach (['h1', 'h2', 'h3', 'h4'] as $tag) {
            preg_match_all("/<{$tag}[^>]*>/i", $html, $matches);
            $order = array_merge($order, array_fill(0, count($matches[0]), $tag));
        }

        return [
            'total' => count($order),
            'order' => empty($order) ? '-' : implode('→', $order),
        ];
    }

    private function countImages(string $html): array
    {
        preg_match_all('/<img[^>]+>/i', $html, $matches);
        $total = count($matches[0]);
        $withAlt = 0;
        $webp = 0;

        foreach ($matches[0] as $img) {
            if (preg_match('/alt\s*=\s*["\']([^"\']*)["\']/i', $img) && !empty(trim($img))) {
                $withAlt++;
            }
            if (str_contains($img, '.webp') || str_contains($img, 'webp')) {
                $webp++;
            }
        }

        return ['total' => $total, 'with_alt' => $withAlt, 'webp' => $webp];
    }

    private function countLinks(string $html): array
    {
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        $internal = 0;
        $external = 0;

        foreach ($matches[1] as $href) {
            if (str_starts_with($href, 'http') && !str_contains($href, parse_url(config('app.url'), PHP_URL_HOST))) {
                $external++;
            } else {
                $internal++;
            }
        }

        return ['internal' => $internal, 'external' => $external];
    }

    private function keywordDensity(string $text, string $keyword): float
    {
        if (empty($keyword) || empty($text)) {
            return 0;
        }

        $wordCount = $this->countWords($text);
        $keywordCount = mb_substr_count(mb_strtolower($text), mb_strtolower($keyword));

        return $wordCount > 0 ? round(($keywordCount / $wordCount) * 100, 2) : 0;
    }

    private function readabilityScore(string $text): float
    {
        $words = $this->countWords($text);
        $sentences = $this->countSentences($text);
        $syllables = $this->estimateSyllables($text);

        if ($words === 0 || $sentences === 0) {
            return 100;
        }

        $score = 206.835 - 1.015 * ($words / $sentences) - 84.6 * ($syllables / $words);

        return round(max(0, min(100, $score)), 1);
    }

    private function estimateSyllables(string $text): int
    {
        $words = preg_split('/\s+/', $text);
        $total = 0;

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) continue;

            $vowelCount = preg_match_all('/[aeiouAEIOUaiueoAIUEO]/', $word);
            $total += max(1, $vowelCount);
        }

        return $total;
    }

    private function estimateComplexWordRatio(string $text): float
    {
        $words = preg_split('/\s+/', $text);
        $total = 0;
        $complex = 0;

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) continue;

            $total++;
            $syllables = preg_match_all('/[aeiouAEIOUaiueoAIUEO]/', $word);
            if ($syllables >= 4) {
                $complex++;
            }
        }

        return $total > 0 ? round(($complex / $total) * 100) : 0;
    }

    private function estimatePassiveVoice(string $text): int
    {
        $passivePatterns = ['di-', 'ter-', 'ke-an'];
        $sentences = preg_split('/[.!?]+/', $text);
        $passiveCount = 0;

        foreach ($sentences as $sentence) {
            foreach ($passivePatterns as $pattern) {
                if (str_contains($sentence, $pattern)) {
                    $passiveCount++;
                    break;
                }
            }
        }

        $total = count($sentences);
        return $total > 0 ? (int) round(($passiveCount / $total) * 100) : 0;
    }

    private function calculateSeoScore(float $density, int $words, array $headings, array $links, string $keyword): int
    {
        $score = 0;

        if ($density >= 1 && $density <= 3) $score += 20;
        elseif ($density > 0) $score += 10;

        if ($words >= 1000) $score += 15;
        elseif ($words >= 500) $score += 10;

        if ($headings['total'] >= 3) $score += 15;
        elseif ($headings['total'] >= 1) $score += 8;

        if ($links['internal'] >= 2) $score += 10;
        elseif ($links['internal'] >= 1) $score += 5;

        if ($links['external'] >= 1) $score += 5;

        if (!empty($keyword) && str_contains($headings['order'] ?? '', 'h1')) $score += 5;

        return min(100, $score + 30);
    }

    private function calculateStructureScore(int $words, float $avgWordsPerSentence, int $paragraphs, array $headings, int $sentences, float $avgSentencesPerParagraph): int
    {
        $score = 0;

        if ($words >= 1000) $score += 15;
        elseif ($words >= 600) $score += 10;
        elseif ($words >= 300) $score += 5;

        if ($avgWordsPerSentence >= 10 && $avgWordsPerSentence <= 20) $score += 15;
        elseif ($avgWordsPerSentence < 10) $score += 8;
        else $score += 10;

        if ($avgSentencesPerParagraph >= 3 && $avgSentencesPerParagraph <= 5) $score += 15;

        if ($paragraphs >= 5) $score += 10;
        elseif ($paragraphs >= 3) $score += 5;

        if ($headings['total'] >= 4) $score += 15;
        elseif ($headings['total'] >= 2) $score += 8;

        return min(100, $score + 30);
    }

    private function calculateReadabilityScore(float $readability, int $passiveVoice): int
    {
        $score = 0;

        if ($readability >= 60) $score += 40;
        elseif ($readability >= 40) $score += 25;
        else $score += 10;

        if ($passiveVoice <= 20) $score += 20;
        elseif ($passiveVoice <= 30) $score += 10;

        return min(100, $score + 40);
    }

    private function calculateImageScore(array $images): int
    {
        if ($images['total'] === 0) return 20;

        $score = 30;
        if ($images['with_alt'] === $images['total']) $score += 30;
        else $score += 10;

        if ($images['webp'] === $images['total'] && $images['total'] > 0) $score += 20;
        elseif ($images['webp'] > 0) $score += 10;

        return min(100, $score);
    }

    private function findIssues(float $density, int $words, array $headings, array $links, float $avgWordsPerSentence, float $readability, int $passiveVoice, array $images, int $paragraphs, float $avgSentencesPerParagraph, string $keyword): array
    {
        $issues = [];

        if ($density > 5) $issues[] = "Keyword density {$density}% terlalu tinggi (ideal 1-3%)";
        if ($density < 0.5 && !empty($keyword)) $issues[] = "Keyword density {$density}% terlalu rendah, tambah keyword secara natural";
        if ($words < 500) $issues[] = "Total kata {$words} terlalu sedikit (minimal 500, ideal ≥1000)";
        if ($avgWordsPerSentence > 25) $issues[] = "Rata-rata kata per kalimat {$avgWordsPerSentence} terlalu panjang, pecah kalimat";
        if ($avgWordsPerSentence < 6) $issues[] = "Rata-rata kata per kalimat {$avgWordsPerSentence} terlalu pendek, kombinasikan kalimat";
        if ($paragraphs < 3) $issues[] = "Total paragraf {$paragraphs} terlalu sedikit (minimal 5)";
        if ($avgSentencesPerParagraph < 2) $issues[] = "Rata-rata {$avgSentencesPerParagraph} kalimat per paragraf, perlu dikembangkan (ideal 3-5)";
        if ($headings['total'] < 2) $issues[] = "Kurang heading (H2/H3) untuk struktur artikel yang baik";
        if ($links['internal'] < 2) $issues[] = "Kurang {$links['internal']} internal link (minimal 2 per artikel)";
        if ($links['external'] < 1) $issues[] = "Tidak ada external link, tambahkan minimal 1 link ke sumber kredibel";
        if ($readability < 40) $issues[] = "Skor readability {$readability} terlalu rendah, gunakan kalimat lebih pendek dan kata lebih sederhana";
        if ($passiveVoice > 30) $issues[] = "Kalimat pasif {$passiveVoice}% terlalu tinggi (ideal ≤20%)";
        if ($images['total'] === 0) $issues[] = "Tidak ada gambar, tambahkan minimal 1 gambar per 500 kata";

        return $issues;
    }
}
