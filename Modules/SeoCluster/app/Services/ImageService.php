<?php

namespace Modules\SeoCluster\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;

class ImageService
{
    public function searchDuckDuckGo(string $keyword, int $count = 10): array
    {
        $results = [];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; SEOAgent/1.0)',
                    'Referer' => 'https://duckduckgo.com/',
                ])
                ->get('https://duckduckgo.com/i.js', [
                    'q' => $keyword,
                    'limit' => $count,
                    'o' => 'json',
                    'vqd' => $this->getVqd($keyword),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $images = $data['results'] ?? [];

                foreach ($images as $img) {
                    $url = $img['image'] ?? $img['thumbnail'] ?? null;
                    $width = (int) ($img['width'] ?? 0);
                    $height = (int) ($img['height'] ?? 0);

                    if (!$url || !str_contains($url, 'http')) {
                        continue;
                    }

                    $results[] = [
                        'url' => $url,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
            }
        } catch (Exception $e) {
            Log::warning('ImageService: DuckDuckGo search failed', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    public function searchImages(string $keyword, int $count = 10): array
    {
        $results = $this->searchDuckDuckGo($keyword, $count);

        if (empty($results)) {
            $results = $this->searchBingImages($keyword, $count);
        }

        return $results;
    }

    public function searchBingImages(string $keyword, int $count = 10): array
    {
        $results = [];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get('https://www.bing.com/images/search', [
                    'q' => $keyword,
                    'qft' => '+filterui:imagesize-large',
                    'form' => 'HDRSC2',
                    'first' => 1,
                    'count' => $count,
                ]);

            if ($response->successful()) {
                $html = $response->body();

                preg_match_all('/murl&quot;:&quot;([^&]+)&quot;/i', $html, $murls);
                preg_match_all('/&quot;murl&quot;:&quot;([^&]+)&quot;/i', $html, $murls2);

                $urls = array_merge($murls[1] ?? [], $murls2[1] ?? []);

                if (empty($urls)) {
                    preg_match_all('/murl":"(https?:[^"]+)"/i', $html, $murls3);
                    $urls = $murls3[1] ?? [];
                }

                $widths = [];
                preg_match_all('/&quot;m&quot;:(\d+)/i', $html, $w1);
                preg_match_all('/"m":(\d+)/i', $html, $w2);
                $widths = array_merge($w1[1] ?? [], $w2[1] ?? []);

                $heights = [];
                preg_match_all('/&quot;h&quot;:(\d+)/i', $html, $h1);
                preg_match_all('/"h":(\d+)/i', $html, $h2);
                $heights = array_merge($h1[1] ?? [], $h2[1] ?? []);

                $urls = array_values(array_unique(array_filter($urls)));

                foreach ($urls as $i => $url) {
                    if (!$url || !str_contains($url, 'http')) {
                        continue;
                    }

                    $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');

                    $results[] = [
                        'url' => $url,
                        'width' => (int) ($widths[$i] ?? 0),
                        'height' => (int) ($heights[$i] ?? 0),
                    ];

                    if (count($results) >= $count) {
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('ImageService: Bing search failed', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    protected function getVqd(string $keyword): string
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SEOAgent/1.0)'])
                ->get('https://duckduckgo.com/', ['q' => $keyword]);

            if ($response->successful()) {
                $html = $response->body();
                if (preg_match('/vqd="([^"]+)"/', $html, $m)) {
                    return $m[1];
                }
            }
        } catch (Exception $e) {
            Log::warning('ImageService: failed to fetch vqd', ['error' => $e->getMessage()]);
        }

        return '';
    }

    public function downloadRandom(string $keyword, int $count = 3, string $source = 'duckduckgo'): array
    {
        $images = $source === 'bing'
            ? $this->searchBingImages($keyword, $count * 3)
            : $this->searchImages($keyword, $count * 3);

        $minWidth = (int) config('seo-cluster.image.min_width', 400);
        $minHeight = (int) config('seo-cluster.image.min_height', 300);

        $filtered = array_values(array_filter($images, function ($img) use ($minWidth, $minHeight) {
            return $img['width'] >= $minWidth && $img['height'] >= $minHeight;
        }));

        $pick = !empty($filtered) ? $filtered : $images;
        shuffle($pick);
        $pick = array_slice($pick, 0, max(1, $count));

        $downloaded = [];
        foreach ($pick as $img) {
            try {
                $tempPath = $this->download($img['url']);
                if ($tempPath) {
                    $downloaded[] = ['tempPath' => $tempPath, 'sourceUrl' => $img['url']];
                }
            } catch (Exception $e) {
                Log::warning('ImageService: download failed', ['error' => $e->getMessage()]);
            }
        }

        return $downloaded;
    }

    public function download(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SEOAgent/1.0)'])
                ->get($url);

            if ($response->failed()) {
                return null;
            }

            $tempDir = storage_path('app/temp/cluster-images');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $path = $tempDir . '/' . uniqid('img_', true) . '.bin';
            file_put_contents($path, $response->body());

            if (!$this->isValidImage($path)) {
                @unlink($path);
                return null;
            }

            return $path;
        } catch (Exception $e) {
            Log::warning('ImageService: download exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function isValidImage(string $path): bool
    {
        $info = @getimagesize($path);
        if (!$info) {
            return false;
        }

        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
        return in_array($info[2], $allowed, true);
    }

    public function convertToWebP(string $sourcePath, int $quality = 80): ?string
    {
        try {
            $tempDir = storage_path('app/temp/cluster-images');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $outputPath = $tempDir . '/' . uniqid('webp_', true) . '.webp';

            $image = Image::decodePath($sourcePath);

            if ($image->width() > 1600 || $image->height() > 1600) {
                $image->scaleDown(1600, 1600);
            }

            $image->save($outputPath, max(1, min(100, $quality)));

            if (!file_exists($outputPath) || !is_file($outputPath)) {
                return null;
            }

            return $outputPath;
        } catch (Exception $e) {
            Log::warning('ImageService: WebP conversion failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function fetchAndUpload(string $keyword, WordPressService $wpService, int $count = 3, string $source = 'duckduckgo'): array
    {
        $quality = (int) config('seo-cluster.image.webp_quality', 80);

        $downloaded = $this->downloadRandom($keyword, $count, $source);

        $uploaded = [];
        foreach ($downloaded as $item) {
            try {
                $webpPath = $this->convertToWebP($item['tempPath'], $quality);
                if (!$webpPath) {
                    continue;
                }

                $filename = $this->slugify($keyword) . '-' . uniqid() . '.webp';

                $media = $wpService->uploadMedia($webpPath, $filename, $keyword);

                if (!empty($media['url'])) {
                    $uploaded[] = [
                        'wpId' => $media['id'],
                        'wpUrl' => $media['url'],
                    ];
                }

                @unlink($webpPath);
            } catch (Exception $e) {
                Log::warning('ImageService: fetchAndUpload item failed', ['error' => $e->getMessage()]);
            } finally {
                if (file_exists($item['tempPath'])) {
                    @unlink($item['tempPath']);
                }
            }
        }

        return $uploaded;
    }

    public function suggestImageKeywords(string $content): array
    {
        $keywords = [];

        preg_match_all('/<h[12][^>]*>(.*?)<\/h[12]>/i', $content, $matches);
        foreach ($matches[1] ?? [] as $heading) {
            $text = trim(strip_tags($heading));
            if (strlen($text) >= 4) {
                $keywords[] = $text;
            }
        }

        $plainText = strip_tags(preg_replace('/>\s*</', '> <', $content));
        $words = preg_split('/\s+/', $plainText);
        $stopwords = [
            'untuk', 'dengan', 'yang', 'dari', 'dalam', 'adalah', 'akan', 'pada',
            'kami', 'anda', 'mereka', 'adalah', 'tidak', 'sudah', 'bisa', 'karena',
            'serta', 'agar', 'antara', 'melalui', 'setiap', 'beberapa', 'sebuah',
            'banyak', 'lebih', 'paling', 'jika', 'kepada', 'dalam', 'menjadi',
        ];

        $words = array_values(array_filter($words, function ($w) use ($stopwords) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $w);
            $lower = mb_strtolower($clean);
            return mb_strlen($clean) >= 5
                && mb_strlen($clean) <= 24
                && !in_array($lower, $stopwords, true);
        }));

        $freq = array_count_values(array_map('mb_strtolower', $words));
        arsort($freq);

        foreach (array_slice(array_keys($freq), 0, 10) as $word) {
            $keywords[] = ucfirst($word);
        }

        $keywords = array_values(array_unique(array_filter($keywords)));
        $keywords = array_slice($keywords, 0, 5);

        return $keywords ?: ['indonesia'];
    }

    public function injectImages(string $content, array $images, string $altText): string
    {
        if (empty($images)) {
            return $content;
        }

        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches, PREG_OFFSET_CAPTURE);
        $paragraphs = $matches[0] ?? [];

        if (count($paragraphs) < 2) {
            return $content;
        }

        $perArticle = (int) config('seo-cluster.image.max_per_article', 3);
        $maxImages = min(count($images), max(1, $perArticle));

        $insertAfter = $this->pickParagraphPositions(count($paragraphs), $maxImages);

        $offset = 0;
        $inserted = 0;

        foreach ($insertAfter as $paraIndex) {
            if ($inserted >= $maxImages) {
                break;
            }

            $pos = $paragraphs[$paraIndex][1] + strlen($paragraphs[$paraIndex][0]) + $offset;
            $image = $images[$inserted];

            $figure = sprintf(
                '<figure><img src="%s" alt="%s" loading="lazy"><figcaption>%s</figcaption></figure>',
                $image['wpUrl'],
                htmlspecialchars($altText, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($altText, ENT_QUOTES, 'UTF-8')
            );

            $content = substr($content, 0, $pos) . "\n\n" . $figure . substr($content, $pos);
            $offset += strlen($figure) + 2;
            $inserted++;
        }

        return $content;
    }

    protected function pickParagraphPositions(int $totalParagraphs, int $count): array
    {
        if ($totalParagraphs <= 2) {
            return [1];
        }

        $positions = [];
        $step = max(1, (int) floor(($totalParagraphs - 1) / $count));

        for ($i = 1; $i < $totalParagraphs && count($positions) < $count; $i += $step) {
            $positions[] = $i;
        }

        return $positions;
    }

    protected function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s_]+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);

        return trim($text, '-') ?: 'gambar';
    }
}
