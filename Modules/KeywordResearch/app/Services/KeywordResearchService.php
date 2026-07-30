<?php

namespace Modules\KeywordResearch\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KeywordResearchService
{
    public array $tokenUsage = ['tokens_in' => 0, 'tokens_out' => 0];

    protected function cfg(string $key, mixed $default = null): mixed
    {
        $db = Setting::getValue("keyword-research.{$key}");
        if ($db !== null) {
            return $db;
        }
        return config("keyword-research.{$key}", $default);
    }

    public function research(string $keyword, string $locale = 'id', int $lsiCount = 12, int $entitiesCount = 7): array
    {
        $url = Setting::getValue('ai.9router.url', config('keyword-research.providers.9router.url'));
        $apiKey = Setting::getValue('ai.9router.api_key', config('keyword-research.providers.9router.api_key'));
        $model = Setting::getValue('ai.9router.chat_model', config('keyword-research.providers.9router.model', 'openai/gpt-4o'));

        if (!$url) {
            throw new Exception('9Router URL is not configured.');
        }

        $systemPrompt = <<<PROMPT
You are an expert SEO keyword researcher. Given a target keyword, return LSI (Latent Semantic Indexing) keywords and keyword entities.

Return ONLY valid JSON (no markdown, no code fences):

{
  "lsi_keywords": [
    {"keyword": "related phrase 1", "search_volume": "high|medium|low", "relevance": 0.95},
    {"keyword": "related phrase 2", "search_volume": "medium", "relevance": 0.85}
  ],
  "entities": [
    {"name": "Entity Name", "type": "brand|person|place|concept|product|organization", "relevance": 0.9, "mention": "how it relates to the keyword"}
  ]
}

Guidelines for LSI keywords:
- Generate exactly {$lsiCount} semantically related keywords
- Include long-tail variations, synonyms, and related topics
- Estimate search volume as high/medium/low
- Relevance score from 0.0 to 1.0

Guidelines for entities:
- Extract exactly {$entitiesCount} key entities related to the keyword
- Identify entity type correctly
- Provide brief explanation of how each entity relates
- Relevance score from 0.0 to 1.0
PROMPT;

        $userPrompt = "Target keyword: {$keyword}\nLanguage: {$locale}";

        $delay = (int) $this->cfg('request_delay', 2);
        if ($delay > 0) {
            sleep($delay);
        }

        $response = Http::timeout(60)->withHeaders([
            'Authorization' => $apiKey ? "Bearer {$apiKey}" : '',
            'Content-Type' => 'application/json',
        ])->post("{$url}/chat/completions", [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 8192,
            'stream' => false,
        ]);

        if ($response->failed()) {
            Log::error('KeywordResearch AI Failed', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);
            throw new Exception('Gagal memproses riset keyword. Silakan coba lagi.');
        }

        $data = $response->json();

        $usage = $data['usage'] ?? [];
        $this->tokenUsage['tokens_in'] += $usage['prompt_tokens'] ?? 0;
        $this->tokenUsage['tokens_out'] += $usage['completion_tokens'] ?? 0;

        $content = $data['choices'][0]['message']['content'] ?? '';

        $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content));

        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            Log::warning('KeywordResearch: failed to parse AI response', ['raw' => $content]);
            return $this->fallback($keyword);
        }

        return [
            'lsi_keywords' => $parsed['lsi_keywords'] ?? [],
            'entities' => $parsed['entities'] ?? [],
            'raw_response' => $parsed,
        ];
    }

    public function sendWebhook(string $url, ?string $secret, array $payload): bool
    {
        try {
            $allowed = config('keyword-research.webhook.allowed_domains', []);
            if (!empty($allowed)) {
                $host = parse_url($url, PHP_URL_HOST);
                $matches = false;
                foreach ($allowed as $domain) {
                    if (str_ends_with($host, '.' . ltrim($domain, '.')) || $host === $domain) {
                        $matches = true;
                        break;
                    }
                }
                if (!$matches) {
                    Log::warning('KeywordResearch webhook blocked: domain not allowed', ['url' => $url]);
                    return false;
                }
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($secret) {
                $headers['X-Webhook-Secret'] = $secret;
                $headers['X-Webhook-Signature'] = hash_hmac('sha256', json_encode($payload), $secret);
            }

            $response = Http::timeout(config('keyword-research.webhook.timeout', 15))
                ->withHeaders($headers)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::warning('KeywordResearch webhook failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::warning('KeywordResearch webhook exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function fallback(string $keyword): array
    {
        return [
            'lsi_keywords' => [
                ['keyword' => "{$keyword} terbaik", 'search_volume' => 'medium', 'relevance' => 0.9],
                ['keyword' => "{$keyword} murah", 'search_volume' => 'high', 'relevance' => 0.85],
                ['keyword' => "cara memilih {$keyword}", 'search_volume' => 'low', 'relevance' => 0.7],
                ['keyword' => "review {$keyword}", 'search_volume' => 'medium', 'relevance' => 0.75],
                ['keyword' => "{$keyword} online", 'search_volume' => 'high', 'relevance' => 0.8],
            ],
            'entities' => [
                ['name' => $keyword, 'type' => 'concept', 'relevance' => 1.0, 'mention' => 'Target keyword utama'],
            ],
            'raw_response' => null,
        ];
    }
}
