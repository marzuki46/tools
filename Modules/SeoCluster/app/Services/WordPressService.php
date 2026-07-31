<?php

namespace Modules\SeoCluster\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressService
{
    protected ?string $baseUrl = null;
    protected ?string $username = null;
    protected ?string $password = null;

    protected function credentials(): array
    {
        if ($this->baseUrl === null) {
            $this->baseUrl = rtrim((string) Setting::getValue('seo-agent.wp.url', config('seo-cluster.wp.url', '')), '/');
            $this->username = (string) Setting::getValue('seo-agent.wp.username', config('seo-cluster.wp.username', ''));
            $this->password = (string) Setting::getValue('seo-agent.wp.password', config('seo-cluster.wp.password', ''));
        }

        return [$this->baseUrl, $this->username, $this->password];
    }

    protected function request(string $method, string $path, array $data = []): array
    {
        [$baseUrl, $username, $password] = $this->credentials();

        if (!$baseUrl) {
            throw new Exception('URL WordPress belum dikonfigurasi.');
        }
        if (!$username || !$password) {
            throw new Exception('Kredensial WordPress belum dikonfigurasi.');
        }

        $http = Http::timeout(120)
            ->withBasicAuth($username, $password)
            ->acceptJson();

        $url = "{$baseUrl}/wp-json/wp/v2/{$path}";

        $response = match (strtoupper($method)) {
            'POST' => $http->post($url, $data),
            'PUT' => $http->put($url, $data),
            'DELETE' => $http->delete($url),
            default => $http->get($url, $data),
        };

        if ($response->failed()) {
            Log::warning('WordPress API request failed', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            $message = $response->json('message');
            throw new Exception('WordPress API error: ' . ($message ?: $response->status()));
        }

        return $response->json();
    }

    public function testConnection(): bool
    {
        try {
            $this->request('GET', 'users/me');
            return true;
        } catch (Exception $e) {
            Log::warning('WordPress connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function publishPost(string $title, string $content, array $meta = []): array
    {
        $data = [
            'title' => $title,
            'content' => $content,
            'status' => $meta['status'] ?? 'publish',
            'excerpt' => $meta['excerpt'] ?? '',
            'slug' => $meta['slug'] ?? null,
            'categories' => $meta['categories'] ?? null,
            'tags' => $meta['tags'] ?? null,
            'comment_status' => 'closed',
        ];

        $data = array_filter($data, fn ($v) => $v !== null);

        if (isset($data['categories']) && !is_array($data['categories'])) {
            $data['categories'] = $data['categories'] ? [$data['categories']] : [];
        }
        if (isset($data['tags']) && !is_array($data['tags'])) {
            $data['tags'] = $data['tags'] ? [$data['tags']] : [];
        }

        $post = $this->request('POST', 'posts', $data);

        return [
            'id' => $post['id'] ?? null,
            'url' => $post['link'] ?? null,
            'status' => $post['status'] ?? 'unknown',
        ];
    }

    public function uploadMedia(string $filePath, string $filename, ?string $altText = null): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File gambar tidak ditemukan: {$filePath}");
        }

        [$baseUrl, $username, $password] = $this->credentials();

        $response = Http::timeout(120)
            ->withBasicAuth($username, $password)
            ->acceptJson()
            ->attach('file', fopen($filePath, 'r'), $filename)
            ->post("{$baseUrl}/wp-json/wp/v2/media");

        if ($response->failed()) {
            Log::warning('WordPress media upload failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            throw new Exception('Gagal upload gambar ke WordPress: ' . ($response->json('message') ?: $response->status()));
        }

        $media = $response->json();

        if ($altText) {
            try {
                $this->request('PUT', "media/{$media['id']}", ['alt_text' => $altText]);
            } catch (Exception $e) {
                Log::warning('Failed to set alt text', ['id' => $media['id'] ?? null]);
            }
        }

        return [
            'id' => $media['id'] ?? null,
            'url' => $media['source_url'] ?? $media['link'] ?? null,
        ];
    }

    public function getExistingPosts(int $limit = 100): array
    {
        $posts = $this->request('GET', 'posts', [
            'per_page' => min(100, $limit),
            'status' => 'publish',
            '_fields' => 'id,link,title,slug',
        ]);

        return array_map(fn ($p) => [
            'id' => $p['id'],
            'title' => is_array($p['title'] ?? null) ? strip_tags($p['title']['rendered'] ?? '') : ($p['title'] ?? ''),
            'slug' => $p['slug'] ?? '',
            'url' => $p['link'] ?? '',
        ], is_array($posts) ? $posts : []);
    }

    public function getPostContent(int $postId): array
    {
        $post = $this->request('GET', "posts/{$postId}", [
            '_fields' => 'id,link,title,content,slug',
        ]);

        return [
            'id' => $post['id'] ?? $postId,
            'title' => is_array($post['title'] ?? null) ? strip_tags($post['title']['rendered'] ?? '') : ($post['title'] ?? ''),
            'slug' => $post['slug'] ?? '',
            'url' => $post['link'] ?? '',
            'content' => is_array($post['content'] ?? null) ? ($post['content']['rendered'] ?? '') : ($post['content'] ?? ''),
        ];
    }

    public function getCategories(): array
    {
        return $this->request('GET', 'categories', ['per_page' => 100, 'hide_empty' => false]);
    }

    public function getTags(): array
    {
        return $this->request('GET', 'tags', ['per_page' => 100, 'hide_empty' => false]);
    }

    public function findOrCreateCategory(string $name): int
    {
        $name = trim($name);
        if (!$name) {
            return 0;
        }

        $categories = $this->getCategories();
        foreach ($categories as $cat) {
            if (($cat['name'] ?? '') === $name) {
                return (int) $cat['id'];
            }
        }

        $created = $this->request('POST', 'categories', ['name' => $name]);
        return (int) ($created['id'] ?? 0);
    }

    public function findOrCreateTag(string $name): int
    {
        $name = trim($name);
        if (!$name) {
            return 0;
        }

        $tags = $this->getTags();
        foreach ($tags as $tag) {
            if (($tag['name'] ?? '') === $name) {
                return (int) $tag['id'];
            }
        }

        $created = $this->request('POST', 'tags', ['name' => $name]);
        return (int) ($created['id'] ?? 0);
    }

    public function createSlug(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s_]+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);

        return trim($text, '-') ?: 'artikel-' . now()->timestamp;
    }
}
