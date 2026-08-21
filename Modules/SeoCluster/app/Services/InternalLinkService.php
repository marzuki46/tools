<?php

namespace Modules\SeoCluster\Services;

use Illuminate\Support\Str;

class InternalLinkService
{
    public function findLinkOpportunities(string $content, array $existingPosts): array
    {
        $plainText = strip_tags($content);
        $plainLower = mb_strtolower($plainText);

        $opportunities = [];

        foreach ($existingPosts as $post) {
            $title = $post['title'] ?? '';
            $url = $post['url'] ?? '';

            if (!$title || !$url) {
                continue;
            }

            $keywords = $this->extractAnchorCandidates($title);

            foreach ($keywords as $keyword) {
                $keywordLower = mb_strtolower($keyword);

                if (strlen($keywordLower) < 4) {
                    continue;
                }

                $alreadyLinked = $this->containsLinkWithAnchor($content, $keyword);
                if ($alreadyLinked) {
                    continue;
                }

                if (mb_strpos($plainLower, $keywordLower) !== false) {
                    $opportunities[] = [
                        'keyword' => $keyword,
                        'postUrl' => $url,
                        'anchorText' => $keyword,
                    ];
                    break;
                }
            }
        }

        return $opportunities;
    }

    public function injectLinks(string $content, array $opportunities, int $maxLinks = 2): string
    {
        if (empty($opportunities)) {
            return $content;
        }

        $maxLinks = max(1, $maxLinks);
        $opportunities = array_slice($opportunities, 0, $maxLinks);

        foreach ($opportunities as $opp) {
            $content = $this->linkFirstOccurrence(
                $content,
                $opp['keyword'],
                $opp['postUrl'],
                $opp['anchorText']
            );
        }

        return $content;
    }

    public function injectSingleLink(string $content, string $keyword, string $url, string $anchorText): string
    {
        if (!$keyword || !$url) {
            return $content;
        }

        return $this->linkFirstOccurrence($content, $keyword, $url, $anchorText);
    }

    protected function linkFirstOccurrence(string $content, string $keyword, string $url, string $anchorText): string
    {
        $matches = [];
        preg_match_all('/<a[^>]*>.*?<\/a>/is', $content, $matches, PREG_OFFSET_CAPTURE);

        $skipRanges = array_map(fn ($m) => [$m[1], $m[1] + strlen($m[0])], $matches[0] ?? []);

        $contentLower = mb_strtolower($content);

        $candidates = array_values(array_unique([
            mb_strtolower($keyword),
            mb_strtolower(Str::ucfirst($keyword)),
        ]));

        usort($candidates, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($candidates as $candidate) {
            $searchFrom = 0;

            while (($pos = mb_strpos($contentLower, $candidate, $searchFrom)) !== false) {
                if ($this->inRange($pos, mb_strlen($candidate), $skipRanges)) {
                    $searchFrom = $pos + mb_strlen($candidate);
                    continue;
                }

                $original = mb_substr($content, $pos, mb_strlen($candidate));

                $before = mb_substr($content, 0, $pos);
                $after = mb_substr($content, $pos + mb_strlen($candidate));

                $link = sprintf(
                    '<a href="%s" title="%s">%s</a>',
                    $url,
                    htmlspecialchars($anchorText, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($original, ENT_QUOTES, 'UTF-8')
                );

                return $before . $link . $after;
            }
        }

        return $content;
    }

    protected function inRange(int $pos, int $length, array $ranges): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($pos < $end && ($pos + $length) > $start) {
                return true;
            }
        }

        return false;
    }

    protected function containsLinkWithAnchor(string $content, string $anchor): bool
    {
        preg_match_all('/<a[^>]*>(.*?)<\/a>/is', $content, $matches);

        foreach ($matches[1] as $inner) {
            if (mb_strpos(mb_strtolower($inner), mb_strtolower($anchor)) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function extractAnchorCandidates(string $title): array
    {
        $title = strip_tags($title);
        $title = trim($title);

        $candidates = [$title];

        $parts = preg_split('/\s*[|—–\-:]\s*|\s{2,}/', $title);
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => strlen($p) >= 4));

        if (count($parts) > 1) {
            foreach ($parts as $part) {
                $candidates[] = $part;
            }

            $longest = array_values(array_filter($parts, fn ($p) => strlen($p) > 20));
            if (!empty($longest)) {
                usort($longest, fn ($a, $b) => strlen($b) - strlen($a));
                $candidates[] = $longest[0];
            }
        }

        $words = preg_split('/\s+/', $title);
        $words = array_values(array_filter($words, fn ($w) => strlen(trim($w)) >= 4));

        for ($n = 2; $n <= 4; $n++) {
            for ($i = 0; $i + $n <= count($words); $i++) {
                $phrase = trim(implode(' ', array_slice($words, $i, $n)));
                if (strlen($phrase) >= 8) {
                    $candidates[] = $phrase;
                }
            }
        }

        usort($candidates, fn ($a, $b) => strlen($b) - strlen($a));

        return array_values(array_unique($candidates));
    }
}
