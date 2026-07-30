<?php

namespace App\Services\SeoAgent;

class ContentQualityChecker
{
    public function checkLength(string $content): array
    {
        $plain = strip_tags($content);
        $words = str_word_count($plain, 1);
        $charCount = mb_strlen($plain);
        $wordCount = count($words);
        $sentences = preg_split('/[.!?]+/', $plain, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = count($sentences);
        $readingTime = max(1, ceil($wordCount / 200));

        $avgWordsPerSentence = $sentenceCount > 0 ? round($wordCount / $sentenceCount, 1) : 0;

        $paragraphs = array_filter(explode("\n", $content), fn($p) => trim(strip_tags($p)) !== '');
        $paragraphCount = count($paragraphs);
        $avgWordsPerParagraph = $paragraphCount > 0 ? round($wordCount / $paragraphCount, 1) : 0;

        $shortParagraphs = 0;
        foreach ($paragraphs as $p) {
            $clean = trim(strip_tags($p));
            if (str_word_count($clean) < 20) {
                $shortParagraphs++;
            }
        }

        return [
            'characters' => $charCount,
            'words' => $wordCount,
            'sentences' => $sentenceCount,
            'paragraphs' => $paragraphCount,
            'reading_time_minutes' => $readingTime,
            'avg_words_per_sentence' => $avgWordsPerSentence,
            'avg_words_per_paragraph' => $avgWordsPerParagraph,
            'short_paragraphs' => $shortParagraphs,
            'word_range' => $this->getWordRangeLabel($wordCount),
        ];
    }

    public function checkReadability(string $content): array
    {
        $plain = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $plain, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = count($sentences);
        $words = str_word_count($plain, 1);
        $wordCount = count($words);
        $syllableCount = $this->countSyllables($plain);

        if ($sentenceCount === 0 || $wordCount === 0) {
            return ['score' => 0, 'level' => 'Tidak ada konten', 'issues' => []];
        }

        // Flesch-Kincaid Grade Level adapted for Indonesian
        $score = 206.835 - 1.015 * ($wordCount / $sentenceCount) - 84.6 * ($syllableCount / $wordCount);
        $score = max(0, min(100, round($score)));

        $issues = [];

        // Check long sentences
        $longSentences = 0;
        foreach ($sentences as $s) {
            if (str_word_count($s) > 25) $longSentences++;
        }
        if ($longSentences > 0) {
            $issues[] = "{$longSentences} kalimat terlalu panjang (>25 kata)";
        }

        // Check short paragraphs
        $paragraphs = array_filter(explode("\n", $plain), fn($p) => trim($p) !== '');
        $shortPars = 0;
        foreach ($paragraphs as $p) {
            if (str_word_count($p) < 15) $shortPars++;
        }
        if ($shortPars > 0) {
            $issues[] = "{$shortPars} paragraf terlalu pendek (<15 kata)";
        }

        // Check complex words
        $complexWords = 0;
        foreach ($words as $w) {
            if ($this->countSyllables($w) >= 4) $complexWords++;
        }
        $complexRatio = $wordCount > 0 ? round($complexWords / $wordCount * 100, 1) : 0;
        if ($complexRatio > 15) {
            $issues[] = "{$complexRatio}% kata sulit (≥4 suku kata) — terlalu tinggi";
        }

        $level = $this->getReadabilityLevel($score);

        return [
            'score' => $score,
            'level' => $level,
            'avg_syllables_per_word' => $wordCount > 0 ? round($syllableCount / $wordCount, 2) : 0,
            'avg_words_per_sentence' => round($wordCount / $sentenceCount, 1),
            'complex_word_ratio' => $complexRatio,
            'long_sentences' => $longSentences,
            'short_paragraphs' => $shortPars,
            'issues' => $issues,
        ];
    }

    protected function countSyllables(string $text): int
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z]/', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $total = 0;

        foreach ($words as $word) {
            $total += $this->syllableCountWord($word);
        }

        return $total;
    }

    protected function syllableCountWord(string $word): int
    {
        $word = trim($word);
        if (empty($word)) return 0;
        $word = preg_replace('/[^a-z]/', '', $word);
        if (empty($word)) return 1;

        // Indonesian: count vowel groups
        $vowels = preg_match_all('/[aiueo]+/', $word, $m);
        $count = $vowels ?: 1;

        // Adjust for silent e at end
        if (str_ends_with($word, 'e') && strlen($word) > 2) {
            $count = max(1, $count - 1);
        }

        return $count;
    }

    protected function getWordRangeLabel(int $words): string
    {
        if ($words < 300) return 'Sangat pendek';
        if ($words < 600) return 'Pendek';
        if ($words < 1000) return 'Sedang';
        if ($words < 1500) return 'Panjang';
        return 'Sangat panjang';
    }

    protected function getReadabilityLevel(int $score): string
    {
        if ($score >= 90) return 'Sangat mudah dibaca';
        if ($score >= 80) return 'Mudah dibaca';
        if ($score >= 70) return 'Cukup mudah';
        if ($score >= 60) return 'Standar';
        if ($score >= 50) return 'Agak sulit';
        if ($score >= 30) return 'Sulit';
        return 'Sangat sulit';
    }
}
