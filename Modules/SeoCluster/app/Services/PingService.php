<?php

namespace Modules\SeoCluster\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PingService
{
    public function pingGoogle(string $postUrl): bool
    {
        try {
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<methodCall>
  <methodName>weblogUpdates.extendedPing</methodName>
  <params>
    <param><value><string>{$postUrl}</string></value></param>
    <param><value><string>{$postUrl}</string></value></param>
    <param><value><string></string></value></param>
    <param><value><string>{$postUrl}</string></value></param>
  </params>
</methodCall>
XML;

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'text/xml',
                    'User-Agent' => 'Mozilla/5.0 (compatible; SEOAgent/1.0)',
                ])
                ->withBody($xml, 'text/xml')
                ->post('https://blogsearch.google.com/ping/RPC2');

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('PingService: Google ping failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function pingBing(string $postUrl): bool
    {
        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post('https://www.bing.com/ping?sitemap=' . urlencode($postUrl));

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('PingService: Bing ping failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function pingIndexNow(string $postUrl, string $host): array
    {
        $key = config('seo-cluster.ping.indexnow_key', env('INDEXNOW_KEY'));

        if (!$key) {
            return ['success' => false, 'message' => 'IndexNow key tidak dikonfigurasi.'];
        }

        try {
            $payload = [
                'host' => $host,
                'key' => $key,
                'keyLocation' => "https://{$host}/{$key}.txt",
                'urlList' => [$postUrl],
            ];

            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json; charset=utf-8'])
                ->post('https://api.indexnow.org/indexnow', $payload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'IndexNow ping berhasil.' : 'IndexNow ping gagal.',
            ];
        } catch (\Exception $e) {
            Log::warning('PingService: IndexNow ping failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function pingAll(string $postUrl): array
    {
        $host = parse_url($postUrl, PHP_URL_HOST) ?: '';

        $results = [
            'google' => $this->pingGoogle($postUrl),
            'bing' => $this->pingBing($postUrl),
        ];

        if ($host) {
            $indexNow = $this->pingIndexNow($postUrl, $host);
            $results['indexnow'] = $indexNow['success'];
        }

        return $results;
    }
}
