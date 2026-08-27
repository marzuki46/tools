<?php

namespace Modules\GeoContent\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\GeoContent\Models\GeoProject;
use Modules\GeoContent\Models\GeoSourceFact;

class CompetitorFactService
{
    public function __construct(
        protected BrandScrubberService $scrubber,
    ) {}

    /**
     * Fetch and sanitize facts from competitor URLs for a project.
     * Creates GeoSourceFact rows (one per URL + one synthesis).
     */
    public function fetchForProject(GeoProject $project): array
    {
        $urls = $project->competitor_urls ?? [];
        $maxUrls = (int) config('geo-content.fetch.max_urls', 5);
        $urls = array_slice(array_filter($urls), 0, $maxUrls);

        if (empty($urls)) {
            throw new \Exception('Daftar URL kompetitor kosong.');
        }

        $allBrands = $project->competitor_brands ?? [];
        $facts = [];

        foreach ($urls as $url) {
            $url = trim($url);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                GeoSourceFact::create([
                    'geo_project_id' => $project->id,
                    'source_url' => $url,
                    'fetch_status' => 'failed',
                    'fetch_error' => 'URL tidak valid',
                ]);
                continue;
            }

            // SSRF: only http/https, block private ranges via simple check
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                GeoSourceFact::create([
                    'geo_project_id' => $project->id,
                    'source_url' => $url,
                    'fetch_status' => 'failed',
                    'fetch_error' => 'Scheme tidak diizinkan',
                ]);
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST) ?? '';
            // Block private/local
            if (preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/i', $host)) {
                GeoSourceFact::create([
                    'geo_project_id' => $project->id,
                    'source_url' => $url,
                    'source_host' => $host,
                    'fetch_status' => 'failed',
                    'fetch_error' => 'Host privat diblokir',
                ]);
                continue;
            }

            try {
                $timeout = (int) config('geo-content.fetch.timeout', 15);
                $response = Http::timeout($timeout)
                    ->withHeaders(['User-Agent' => 'JukiGeoBot/1.0 (+https://tools.juki.eu.org)'])
                    ->get($url);

                if (!$response->successful()) {
                    throw new \Exception('HTTP ' . $response->status());
                }

                $html = $response->body();
                $maxBytes = (int) config('geo-content.fetch.max_bytes', 5242880);
                if (strlen($html) > $maxBytes) {
                    $html = substr($html, 0, $maxBytes);
                }

                // Extract brands from this page
                $pageBrands = $this->scrubber->extractBrands($url, $html);
                $allBrands = array_values(array_unique(array_merge($allBrands, $pageBrands)));

                // Extract text content (strip tags, keep headings/paragraphs)
                $text = $this->extractText($html);
                $sanitized = $this->scrubber->scrub($text, $pageBrands);

                // Deduplicate: simple hash check
                $hash = hash('sha256', $sanitized);

                GeoSourceFact::create([
                    'geo_project_id' => $project->id,
                    'source_url' => $url,
                    'source_host' => $host,
                    'raw_text' => mb_substr($text, 0, 20000),
                    'sanitized_facts' => mb_substr($sanitized, 0, 20000),
                    'content_hash' => $hash,
                    'is_synthesis' => false,
                    'fetch_status' => 'success',
                ]);

                $facts[] = $sanitized;
            } catch (\Throwable $e) {
                Log::warning('GeoContent: fetch kompetitor gagal', ['url' => $url, 'error' => $e->getMessage()]);
                GeoSourceFact::create([
                    'geo_project_id' => $project->id,
                    'source_url' => $url,
                    'source_host' => $host,
                    'fetch_status' => 'failed',
                    'fetch_error' => mb_substr($e->getMessage(), 0, 500),
                ]);
            }
        }

        // Update project brands
        if (!empty($allBrands)) {
            $project->update(['competitor_brands' => array_values(array_unique($allBrands))]);
        }

        // Create synthesis fact (gabungan semua fakta ter-sanitasi)
        $synthesis = $this->synthesize($facts, $project->keyword_utama);
        if ($synthesis !== '') {
            GeoSourceFact::create([
                'geo_project_id' => $project->id,
                'source_url' => 'synthesis',
                'source_host' => 'synthesis',
                'sanitized_facts' => $synthesis,
                'content_hash' => hash('sha256', $synthesis),
                'is_synthesis' => true,
                'fetch_status' => 'success',
            ]);
        }

        return $facts;
    }

    protected function extractText(string $html): string
    {
        // Remove scripts/styles
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Extract headings and paragraphs
        $textParts = [];
        if (preg_match_all('/<(h[1-3]|p|li)[^>]*>(.*?)<\/\1>/is', $html, $matches)) {
            foreach ($matches[2] as $inner) {
                $clean = trim(strip_tags($inner));
                if (mb_strlen($clean) >= 20) {
                    $textParts[] = $clean;
                }
            }
        }

        if (empty($textParts)) {
            $textParts[] = trim(strip_tags($html));
        }

        $text = implode("\n\n", $textParts);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim(mb_substr($text, 0, 30000));
    }

    protected function synthesize(array $facts, string $keyword): string
    {
        if (empty($facts)) {
            return '';
        }
        // Simple concatenation with deduplication
        $seen = [];
        $unique = [];
        foreach ($facts as $fact) {
            $hash = md5(mb_substr($fact, 0, 500));
            if (!isset($seen[$hash])) {
                $seen[$hash] = true;
                $unique[] = $fact;
            }
        }
        $combined = implode("\n\n---\n\n", $unique);
        return mb_substr($combined, 0, 25000);
    }

    public function getSynthesis(GeoProject $project): string
    {
        $row = GeoSourceFact::where('geo_project_id', $project->id)->where('is_synthesis', true)->latest()->first();
        if ($row) {
            return (string) $row->sanitized_facts;
        }
        // Fallback: gabung semua sanitized
        $rows = GeoSourceFact::where('geo_project_id', $project->id)->where('is_synthesis', false)->where('fetch_status', 'success')->get();
        return $rows->pluck('sanitized_facts')->implode("\n\n---\n\n");
    }
}
