<?php

namespace Modules\ContentGenerator\Services;

use Illuminate\Support\Facades\Log;

class NoAiSlopService
{
    protected array $bannedWords = [
        'id' => [
            'memfasilitasi',
            'memberdayakan',
            'pergeseran paradigma',
            'game changer',
            'mengubah segalanya',
            'revolusioner',
            'transformatif',
            'mutakhir',
            'komprehensif',
            'menyeluruh',
            'dengan mulus',
            'di era modern',
            'di era digital',
            'di era sekarang',
            'di dunia yang',
            'penting untuk dicatat',
            'perlu digarisbawahi',
            'perlu diingat',
            'patut dicatat',
            'tidak dapat dipungkiri',
            'tidak bisa dipungkiri',
            'kenyataannya adalah',
            'faktanya adalah',
            'sesungguhnya',
            'mari kita bahas',
            'mari kita mulai',
            'dalam artikel ini',
            'pada intinya',
            'pada dasarnya',
            'secara keseluruhan',
            'tidak diragukan lagi',
            'jelas sekali',
            'sudah pasti',
        ],
        'en' => [
            'delve',
            'foster',
            'leverage',
            'utilize',
            'facilitate',
            'empower',
            'streamline',
            'robust',
            'cutting-edge',
            'paradigm shift',
            'game changer',
            'this is huge',
            'this changes everything',
            'tapestry',
            'realm',
            'beacon',
            'multifaceted',
            'meticulous',
            'intricate',
            'paramount',
            'transformative',
            'elevate',
            'embark',
            'supercharge',
            'harness',
            'ever-evolving',
            "it's worth noting",
            'it is worth noting',
            'it\'s important to note',
            'at the end of the day',
            'when it comes to',
            'at its core',
            'in today\'s world',
            'in the age of',
            'in the world of',
            'the reality is',
            'the truth is',
            'in terms of',
            'with regard to',
            'in order to',
            'going forward',
            'let\'s dive in',
        ],
    ];

    protected array $patterns = [
        'emoji_in_heading' => [
            'label' => 'Emoji di heading',
            'regex' => '/^#{1,6}\s.*[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}]/u',
            'severity' => 'strong',
        ],
        'emoji_in_text' => [
            'label' => 'Emoji di teks',
            'regex' => '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}]/u',
            'severity' => 'strong',
        ],
        'binary_contrast_id' => [
            'label' => 'Kontras biner (bukan X, melainkan Y)',
            'regex' => '/\bbukan\s+(?:hanya\s+)?[\w\s&,-]*?\s+(?:tetapi|melainkan)\b/iu',
            'severity' => 'critical',
        ],
        'binary_contrast_en' => [
            'label' => 'Binary contrast (not X, it\'s Y)',
            'regex' => '/\bit\'s not\s+[\w\s&,-]*?\s+it\'s\b/i',
            'severity' => 'critical',
        ],
        'colon_reveal_heading' => [
            'label' => 'Colon reveal di heading (judul dramatis)',
            'regex' => '/^#{1,6}\s+.+:\s*$/u',
            'severity' => 'strong',
        ],
        'metadiscourse_id' => [
            'label' => 'Interpretive metadiscourse',
            'regex' => '/(kuncinya adalah|yang terpenting|perlu digarisbawahi|perhatikan bahwa|yang perlu dipahami)/iu',
            'severity' => 'strong',
        ],
        'metadiscourse_en' => [
            'label' => 'Interpretive metadiscourse',
            'regex' => '/\b(the key point is|as you can see|this distinction matters|that last part matters|in other words)\b/i',
            'severity' => 'strong',
        ],
        'trailing_ing' => [
            'label' => 'Superficial analysis (trailing -ing)',
            'regex' => '/,\s(?:highlighting|underscoring|reflecting|showcasing|demonstrating)\b/i',
            'severity' => 'critical',
        ],
        'fake_profound' => [
            'label' => 'Kesimpulan sok-dalam',
            'regex' => '/(itulah kuncinya|itulah rahasianya|it\'s that simple|that\'s the whole thing|namun itulah kenyataannya|pada akhirnya yang terpenting)/iu',
            'severity' => 'critical',
        ],
        'em_dash' => [
            'label' => 'Em/en dash (tanda pisah)',
            'regex' => '/[—–]/u',
            'severity' => 'critical',
        ],
        'copula_avoidance_id' => [
            'label' => 'Copula avoidance (berfungsi sebagai)',
            'regex' => '/\bberfungsi sebagai\b|\bmenyuguhkan\b|\bmenghadirkan\b/iu',
            'severity' => 'strong',
        ],
        'copula_avoidance_en' => [
            'label' => 'Copula avoidance (serves as)',
            'regex' => '/\bserves as\b|\bboasts\b|\bstands as\b|\bfeatures\b/i',
            'severity' => 'strong',
        ],
        'weasel_words_id' => [
            'label' => 'Weasel words (attribusi samar)',
            'regex' => '/\b(para ahli|beberapa sumber|banyak pihak|dilaporkan)\b/iu',
            'severity' => 'critical',
        ],
        'weasel_words_en' => [
            'label' => 'Weasel words (vague attribution)',
            'regex' => '/\b(experts believe|experts say|industry reports|some critics argue|observers have cited)\b/i',
            'severity' => 'critical',
        ],
        'chatbot_artifact_id' => [
            'label' => 'Chatbot artifact (jargon obrolan)',
            'regex' => '/(semoga membantu|jangan ragu untuk bertanya|jika ada pertanyaan|ada yang bisa saya bantu)/iu',
            'severity' => 'critical',
        ],
        'chatbot_artifact_en' => [
            'label' => 'Chatbot artifact (conversation jargon)',
            'regex' => '/\b(I hope this helps|let me know if|would you like me to|happy to help)\b/i',
            'severity' => 'critical',
        ],
        'tailing_negation' => [
            'label' => 'Tailing negation (no guessing)',
            'regex' => '/,\s*(?:no guessing|no wasted motion|no guessing games)\b/i',
            'severity' => 'strong',
        ],
        'generic_conclusion_id' => [
            'label' => 'Kesimpulan generik',
            'regex' => '/\bmasa depan\b.{0,40}?\b(?:cerah|menjanjikan|penuh harapan|lebih baik)\b/iu',
            'severity' => 'strong',
        ],
        'generic_conclusion_en' => [
            'label' => 'Generic conclusion',
            'regex' => '/\b(the future looks bright|exciting times lie ahead|a bright future|endless possibilities)\b/i',
            'severity' => 'strong',
        ],
        'excessive_hedging_id' => [
            'label' => 'Hedging berlebih',
            'regex' => '/(mungkin bisa saja|bisa jadi mungkin|mungkin saja bisa|kemungkinan besar mungkin)/iu',
            'severity' => 'strong',
        ],
        'excessive_hedging_en' => [
            'label' => 'Excessive hedging',
            'regex' => '/\b(could potentially possibly|might potentially|possibly could maybe)\b/i',
            'severity' => 'strong',
        ],
        'false_range' => [
            'label' => 'False range (dari X ke Y)',
            'regex' => '/\bfrom\s+[\w\s]{2,40}\s+to\s+[\w\s]{2,40}\b/i',
            'severity' => 'weak',
        ],
        'rule_of_three' => [
            'label' => 'Rule of three (A, B, dan C)',
            'regex' => '/\b\w+,\s*\w+,\s*(?:dan|and)\s+\w+\b/i',
            'severity' => 'weak',
        ],
    ];

    protected function patternLocale(string $key): ?string
    {
        if (str_ends_with($key, '_id')) {
            return 'id';
        }
        if (str_ends_with($key, '_en')) {
            return 'en';
        }
        return null;
    }

    public function scan(string $content, string $locale = 'id'): array
    {
        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        $hits = [];
        $lines = preg_split('/\R/u', $content);

        foreach ($lines as $lineNumber => $line) {
            $lowerLine = mb_strtolower($line);

            foreach ($this->bannedWords as $lang => $words) {
                if ($lang !== 'id' && $lang !== $locale) {
                    continue;
                }
                foreach ($words as $word) {
                    if ($word === '') {
                        continue;
                    }
                    if (mb_strpos($lowerLine, mb_strtolower($word)) !== false) {
                        $hits[] = [
                            'pattern' => 'banned_word',
                            'language' => $lang,
                            'word' => $word,
                            'line' => $lineNumber + 1,
                            'severity' => 'weak',
                            'snippet' => trim(mb_substr($line, 0, 160)),
                        ];
                    }
                }
            }

            foreach ($this->patterns as $key => $pattern) {
                $patternLang = $this->patternLocale($key);
                if ($patternLang !== null && $patternLang !== $locale) {
                    continue;
                }
                if (preg_match($pattern['regex'], $line) === 1) {
                    $hits[] = [
                        'pattern' => $pattern['label'],
                        'language' => $locale,
                        'word' => '',
                        'line' => $lineNumber + 1,
                        'severity' => $pattern['severity'] ?? 'weak',
                        'snippet' => trim(mb_substr($line, 0, 160)),
                    ];
                }
            }
        }

        return $hits;
    }

    public function score(array $hits, string $locale = 'id'): array
    {
        $critical = 0;
        $strong = 0;
        $weakPatterns = [];

        foreach ($hits as $hit) {
            $severity = $hit['severity'] ?? 'weak';
            $patternKey = $hit['pattern'] ?? '';

            if ($severity === 'critical') {
                $critical++;
            } elseif ($severity === 'strong') {
                $strong++;
            } else {
                $weakPatterns[$patternKey] = true;
            }
        }

        return [
            'critical' => $critical,
            'strong' => $strong,
            'weak' => count($weakPatterns),
            'should_rewrite' => $this->shouldRewrite($hits, $locale),
        ];
    }

    public function shouldRewrite(array $hits, string $locale = 'id'): bool
    {
        $critical = 0;
        $strong = 0;
        $weakPatterns = [];

        foreach ($hits as $hit) {
            $severity = $hit['severity'] ?? 'weak';
            if ($severity === 'critical') {
                $critical++;
            } elseif ($severity === 'strong') {
                $strong++;
            } else {
                $weakPatterns[$hit['pattern'] ?? ''] = true;
            }
        }

        if ($critical >= 1) {
            return true;
        }
        if ($strong >= 2) {
            return true;
        }
        if (count($weakPatterns) >= 3) {
            return true;
        }

        return false;
    }

    public function clean(string $content, string $locale = 'id', bool $autoFixBannedWords = false): string
    {
        if (!is_string($content) || trim($content) === '') {
            return $content;
        }

        $hits = $this->scan($content, $locale);

        if (!empty($hits)) {
            $score = $this->score($hits, $locale);
            Log::warning('NoAiSlopService: AI-slop patterns detected', [
                'locale' => $locale,
                'count' => count($hits),
                'score' => $score,
                'hits' => array_slice($hits, 0, 20),
            ]);
        }

        if ($autoFixBannedWords) {
            $content = $this->fixBannedWords($content, $locale);
        }

        return $content;
    }

    protected function fixBannedWords(string $content, string $locale): string
    {
        $replacements = $locale === 'en' ? $this->englishReplacements() : $this->indonesianReplacements();

        foreach ($replacements as $from => $to) {
            if ($from === '') {
                continue;
            }
            $content = preg_replace('/\b' . preg_quote($from, '/') . '\b/iu', $to, $content);
        }

        return $content;
    }

    protected function indonesianReplacements(): array
    {
        return [
            'memfasilitasi' => 'memudahkan',
            'memberdayakan' => 'memperkuat',
            'pergeseran paradigma' => 'perubahan besar',
            'revolusioner' => 'besar',
            'transformatif' => 'besar',
            'mutakhir' => 'terbaru',
            'komprehensif' => 'lengkap',
            'dengan mulus' => 'lancar',
            'penting untuk dicatat' => '',
            'perlu digarisbawahi' => '',
            'perlu diingat' => '',
            'patut dicatat' => '',
            'tidak dapat dipungkiri' => 'jelas',
            'tidak bisa dipungkiri' => 'jelas',
            'kenyataannya adalah' => '',
            'faktanya adalah' => '',
            'mari kita bahas' => '',
            'mari kita mulai' => '',
            'dalam artikel ini' => '',
            'pada intinya' => '',
            'pada dasarnya' => 'secara umum',
            'tidak diragukan lagi' => 'tentu',
            'jelas sekali' => 'jelas',
            'sudah pasti' => 'pasti',
        ];
    }

    protected function englishReplacements(): array
    {
        return [
            'delve' => 'go deeper',
            'foster' => 'encourage',
            'leverage' => 'use',
            'utilize' => 'use',
            'facilitate' => 'help',
            'empower' => 'strengthen',
            'streamline' => 'simplify',
            'robust' => 'reliable',
            'cutting-edge' => 'latest',
            'paradigm shift' => 'major change',
            'game changer' => 'major change',
            'this is huge' => 'this matters',
            'this changes everything' => 'this changes a lot',
            'tapestry' => 'mix',
            'realm' => 'field',
            'beacon' => 'example',
            'multifaceted' => 'complex',
            'meticulous' => 'careful',
            'intricate' => 'detailed',
            'paramount' => 'essential',
            'transformative' => 'major',
            'elevate' => 'raise',
            'embark' => 'start',
            'supercharge' => 'strengthen',
            'harness' => 'use',
            'ever-evolving' => 'constantly changing',
            'it\'s worth noting' => '',
            'it is worth noting' => '',
            'it\'s important to note' => '',
            'in today\'s world' => '',
            'in the age of' => '',
            'the reality is' => '',
            'the truth is' => '',
            'in terms of' => 'for',
            'with regard to' => 'about',
            'in order to' => 'to',
            'going forward' => '',
            'let\'s dive in' => '',
        ];
    }
}