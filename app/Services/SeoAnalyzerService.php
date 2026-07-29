<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoAnalyzerService
{
    public function analyze(string $url, ?string $keyword = null): array
    {
        $html = $this->fetchUrl($url);
        if (!$html) {
            return [
                'url' => $url,
                'score' => 0,
                'grade' => 'F',
                'title' => '',
                'meta_description' => '',
                'keyword' => $keyword ?? '',
                'error' => 'Gagal mengambil halaman. Pastikan URL valid dan dapat diakses.',
                'checks' => [],
            ];
        }

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR);

        $checks = [];
        $totalScore = 0;

        $checks['title'] = $this->checkTitle($dom, $keyword);
        $totalScore += $checks['title']['score'];

        $checks['meta_description'] = $this->checkMetaDescription($dom, $keyword);
        $totalScore += $checks['meta_description']['score'];

        $checks['h1'] = $this->checkH1($dom, $keyword);
        $totalScore += $checks['h1']['score'];

        $checks['headings'] = $this->checkHeadings($dom);
        $totalScore += $checks['headings']['score'];

        $checks['content_length'] = $this->checkContentLength($dom);
        $totalScore += $checks['content_length']['score'];

        $checks['images'] = $this->checkImages($dom, $url);
        $totalScore += $checks['images']['score'];

        $checks['links'] = $this->checkLinks($dom, $url);
        $totalScore += $checks['links']['score'];

        $checks['og_tags'] = $this->checkOgTags($dom);
        $totalScore += $checks['og_tags']['score'];

        $checks['canonical'] = $this->checkCanonical($dom);
        $totalScore += $checks['canonical']['score'];

        $checks['robots'] = $this->checkRobots($dom);
        $totalScore += $checks['robots']['score'];

        $rawTitle = $checks['title']['found'] ?? '';
        $rawDesc = $checks['meta_description']['found'] ?? '';

        $grade = match (true) {
            $totalScore >= 90 => 'A',
            $totalScore >= 75 => 'B',
            $totalScore >= 55 => 'C',
            $totalScore >= 35 => 'D',
            default => 'E',
        };

        return [
            'url' => $url,
            'score' => $totalScore,
            'grade' => $grade,
            'title' => $rawTitle,
            'meta_description' => $rawDesc,
            'keyword' => $keyword ?? '',
            'checks' => $checks,
        ];
    }

    private function fetchUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; JukiTools/1.0; SEO Analyzer)'])
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }
            return null;
        } catch (\Exception $e) {
            Log::warning('SEO Analyzer: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function getMetaContent(DOMDocument $dom, string $name): string
    {
        $xpath = new DOMXPath($dom);
        foreach (['name', 'property'] as $attr) {
            $nodes = $xpath->query("//meta[@" . $attr . "='" . $name . "']/@content");
            if ($nodes && $nodes->length > 0) {
                return trim($nodes->item(0)->nodeValue);
            }
        }
        return '';
    }

    private function countInText(string $text, ?string $keyword): int
    {
        if (!$keyword) return 0;
        return mb_substr_count(mb_strtolower($text), mb_strtolower($keyword));
    }

    private function checkTitle(DOMDocument $dom, ?string $keyword): array
    {
        $titles = $dom->getElementsByTagName('title');
        $title = $titles->length > 0 ? trim($titles->item(0)->textContent) : '';
        $len = mb_strlen($title);
        $score = 0;
        $issues = [];

        if (!$title) {
            $issues[] = '❌ Tag title tidak ditemukan';
        } else {
            if ($len >= 30 && $len <= 60) {
                $score = 10;
            } elseif ($len > 60) {
                $score = 5;
                $issues[] = '⚠️ Title terlalu panjang (' . $len . ' karakter, ideal 30-60)';
            } else {
                $score = 3;
                $issues[] = '⚠️ Title terlalu pendek (' . $len . ' karakter, ideal 30-60)';
            }

            if ($keyword && $this->countInText($title, $keyword) > 0) {
                $score += 5;
            } elseif ($keyword) {
                $issues[] = '❌ Keyword tidak ditemukan di title tag';
            }
        }

        return [
            'score' => $score,
            'max' => 15,
            'found' => $title,
            'length' => $len,
            'issues' => $issues,
            'status' => $score >= 12 ? 'good' : ($score > 0 ? 'warning' : 'bad'),
        ];
    }

    private function checkMetaDescription(DOMDocument $dom, ?string $keyword): array
    {
        $desc = $this->getMetaContent($dom, 'description');
        $len = mb_strlen($desc);
        $score = 0;
        $issues = [];

        if (!$desc) {
            $issues[] = '❌ Meta description tidak ditemukan';
        } else {
            if ($len >= 120 && $len <= 165) {
                $score = 10;
            } elseif ($len > 165) {
                $score = 5;
                $issues[] = '⚠️ Meta description terlalu panjang (' . $len . ' karakter, ideal 120-165)';
            } else {
                $score = 3;
                $issues[] = '⚠️ Meta description terlalu pendek (' . $len . ' karakter, ideal 120-165)';
            }

            if ($keyword && $this->countInText($desc, $keyword) > 0) {
                $score += 5;
            } elseif ($keyword) {
                $issues[] = '❌ Keyword tidak ditemukan di meta description';
            }
        }

        return [
            'score' => $score,
            'max' => 15,
            'found' => $desc,
            'length' => $len,
            'issues' => $issues,
            'status' => $score >= 12 ? 'good' : ($score > 0 ? 'warning' : 'bad'),
        ];
    }

    private function checkH1(DOMDocument $dom, ?string $keyword): array
    {
        $h1s = $dom->getElementsByTagName('h1');
        $count = $h1s->length;
        $score = 0;
        $issues = [];
        $texts = [];

        for ($i = 0; $i < $count; $i++) {
            $texts[] = trim($h1s->item($i)->textContent);
        }

        if ($count === 0) {
            $issues[] = '❌ Tidak ada tag H1';
        } elseif ($count === 1) {
            $score = 10;
            $h1Text = $texts[0];
            if ($keyword && $this->countInText($h1Text, $keyword) > 0) {
                $score += 5;
            } elseif ($keyword) {
                $issues[] = '⚠️ Keyword tidak ditemukan di H1';
            }
        } else {
            $score = 5;
            $issues[] = '⚠️ Terdapat ' . $count . ' tag H1 (sebaiknya hanya 1)';
            if ($keyword) {
                foreach ($texts as $t) {
                    if ($this->countInText($t, $keyword) > 0) { $score += 3; break; }
                }
            }
        }

        return [
            'score' => $score,
            'max' => 15,
            'found' => $count,
            'texts' => $texts,
            'issues' => $issues,
            'status' => $score >= 12 ? 'good' : ($score > 0 ? 'warning' : 'bad'),
        ];
    }

    private function checkHeadings(DOMDocument $dom): array
    {
        $score = 0;
        $issues = [];
        $structure = [];

        foreach (['h1', 'h2', 'h3', 'h4'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            $structure[$tag] = [];
            for ($i = 0; $i < $nodes->length; $i++) {
                $structure[$tag][] = trim($nodes->item($i)->textContent);
            }
        }

        if (count($structure['h2']) >= 2) {
            $score += 5;
        } elseif (count($structure['h2']) === 1) {
            $score += 2;
            $issues[] = '⚠️ Hanya 1 H2, tambahkan lebih banyak sub-bagian';
        } else {
            $issues[] = '⚠️ Tidak ada H2 — struktur heading kurang baik';
        }

        if (count($structure['h3']) > 0) {
            $score += 3;
        }

        if (count($structure['h4']) > 0) {
            $score += 2;
        }

        return [
            'score' => min($score, 10),
            'max' => 10,
            'found' => $structure,
            'issues' => $issues,
            'status' => $score >= 7 ? 'good' : ($score > 0 ? 'warning' : 'bad'),
        ];
    }

    private function checkContentLength(DOMDocument $dom): array
    {
        $body = $dom->getElementsByTagName('body');
        $text = $body->length > 0 ? trim(strip_tags($body->item(0)->textContent)) : '';
        $words = str_word_count($text);
        $score = 0;
        $issues = [];

        if ($words >= 1500) {
            $score = 15;
        } elseif ($words >= 800) {
            $score = 10;
            $issues[] = '⚠️ Konten ' . number_format($words) . ' kata (ideal > 1500 untuk SEO)';
        } elseif ($words >= 300) {
            $score = 5;
            $issues[] = '⚠️ Konten terlalu pendek: ' . number_format($words) . ' kata (minimal 800 direkomendasikan)';
        } else {
            $issues[] = '❌ Konten sangat pendek: ' . number_format($words) . ' kata';
        }

        return [
            'score' => $score,
            'max' => 15,
            'found' => $words,
            'issues' => $issues,
            'status' => $score >= 12 ? 'good' : ($score > 0 ? 'warning' : 'bad'),
        ];
    }

    private function checkImages(DOMDocument $dom, string $baseUrl): array
    {
        $images = $dom->getElementsByTagName('img');
        $total = $images->length;
        $withAlt = 0;
        $missingAlt = 0;
        $issues = [];

        foreach ($images as $img) {
            $alt = trim($img->getAttribute('alt'));
            if ($alt) {
                $withAlt++;
            } else {
                $missingAlt++;
            }
        }

        $score = $total > 0 ? round(($withAlt / $total) * 10) : 0;
        $total && $score = $score ?: 1;

        if ($total === 0) {
            $issues[] = '⚠️ Tidak ada gambar di halaman';
        } elseif ($missingAlt > 0) {
            $issues[] = '❌ ' . $missingAlt . ' dari ' . $total . ' gambar tidak memiliki alt text';
        }

        return [
            'score' => $score,
            'max' => 10,
            'found' => ['total' => $total, 'with_alt' => $withAlt, 'missing_alt' => $missingAlt],
            'issues' => $issues,
            'status' => $score >= 8 ? 'good' : ($score > 0 ? 'warning' : 'bad'),
        ];
    }

    private function checkLinks(DOMDocument $dom, string $baseUrl): array
    {
        $links = $dom->getElementsByTagName('a');
        $internal = 0;
        $external = 0;
        $nofollow = 0;
        $total = 0;
        $issues = [];
        $parsed = parse_url($baseUrl);
        $baseHost = $parsed['host'] ?? '';

        foreach ($links as $link) {
            $href = trim($link->getAttribute('href'));
            if (!$href || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) continue;
            $total++;

            $rel = $link->getAttribute('rel');
            if (str_contains($rel, 'nofollow')) $nofollow++;

            if (str_starts_with($href, 'http')) {
                $host = parse_url($href, PHP_URL_HOST);
                if ($host === $baseHost) $internal++; else $external++;
            } else {
                $internal++;
            }
        }

        $score = 0;
        if ($total > 0) {
            $internalPct = $total > 0 ? round(($internal / $total) * 100) : 0;
            if ($internal >= 3) $score += 5;
            if ($external >= 1) $score += 3;
            if ($nofollow === 0) $score += 2;
        }

        if ($internal === 0) $issues[] = '⚠️ Tidak ada internal link';
        if ($external === 0) $issues[] = '⚠️ Tidak ada external link';

        return [
            'score' => $score,
            'max' => 10,
            'found' => ['total' => $total, 'internal' => $internal, 'external' => $external, 'nofollow' => $nofollow],
            'issues' => $issues,
            'status' => $score >= 7 ? 'good' : ($score > 0 ? 'warning' : 'bad'),
        ];
    }

    private function checkOgTags(DOMDocument $dom): array
    {
        $score = 0;
        $issues = [];

        $ogTitle = $this->getMetaContent($dom, 'og:title');
        $ogDesc = $this->getMetaContent($dom, 'og:description');
        $ogImage = $this->getMetaContent($dom, 'og:image');

        if ($ogTitle) $score += 4; else $issues[] = '❌ og:title tidak ditemukan';
        if ($ogDesc) $score += 3; else $issues[] = '⚠️ og:description tidak ditemukan';
        if ($ogImage) $score += 3; else $issues[] = '⚠️ og:image tidak ditemukan';

        return [
            'score' => $score,
            'max' => 10,
            'found' => ['og:title' => $ogTitle, 'og:description' => $ogDesc, 'og:image' => $ogImage],
            'issues' => $issues,
            'status' => $score >= 8 ? 'good' : ($score > 0 ? 'warning' : 'bad'),
        ];
    }

    private function checkCanonical(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query("//link[@rel='canonical']/@href");
        $canonical = $nodes && $nodes->length > 0 ? $nodes->item(0)->nodeValue : '';

        return [
            'score' => $canonical ? 5 : 0,
            'max' => 5,
            'found' => $canonical ?: null,
            'issues' => $canonical ? [] : ['⚠️ Tag canonical tidak ditemukan'],
            'status' => $canonical ? 'good' : 'warning',
        ];
    }

    private function checkRobots(DOMDocument $dom): array
    {
        $robots = $this->getMetaContent($dom, 'robots');
        $issues = [];

        if ($robots && str_contains($robots, 'noindex')) {
            $issues[] = '❌ Halaman di-set noindex (tidak akan muncul di Google)';
        }
        if ($robots && str_contains($robots, 'nofollow')) {
            $issues[] = '⚠️ Halaman di-set nofollow';
        }

        return [
            'score' => $robots && !str_contains($robots, 'noindex') ? 5 : 0,
            'max' => 5,
            'found' => $robots ?: null,
            'issues' => $issues,
            'status' => empty($issues) ? 'good' : 'bad',
        ];
    }
}
