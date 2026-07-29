<?php

namespace App\Services;

use App\Models\SchemaMarkup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SchemaGeneratorService
{
    public function generate(
        string $schemaType,
        array $data,
        ?string $targetUrl = null,
        ?object $sourceable = null,
        bool $useAi = false
    ): array {
        if ($useAi) {
            return $this->generateWithAi($schemaType, $data, $targetUrl, $sourceable);
        }

        $generated = $this->generateManually($schemaType, $data, $targetUrl, $sourceable);
        $this->validateSchema($generated);

        return $generated;
    }

    public function autoFillFromContent(string $schemaType, object $content, ?object $businessProfile = null): array
    {
        $data = [
            'target_url' => '',
            'name' => '',
        ];

        $contentData = [
            'title' => $content->meta_title ?: $content->phase_1_content ?? '',
            'description' => $content->meta_description ?: '',
            'body' => $content->phase_3_content ?: $content->phase_1_content ?? '',
            'keyword' => $content->target_keyword ?? '',
            'created_at' => $content->created_at?->toIso8601String(),
        ];

        if ($schemaType === 'Article') {
            $data = array_merge($data, [
                'headline' => $contentData['title'],
                'description' => $contentData['description'],
                'article_body' => $contentData['body'],
                'keyword' => $contentData['keyword'],
                'date_published' => $contentData['created_at'],
                'date_modified' => $contentData['created_at'],
                'author_name' => $content->user?->name ?? '',
                'author_type' => 'Person',
            ]);
        } elseif ($schemaType === 'FAQPage') {
            $questions = $content->phase_2_questions ?? [];
            $faqItems = [];
            foreach ($questions as $q) {
                $faqItems[] = [
                    'question' => is_string($q) ? $q : ($q['question'] ?? ''),
                    'answer' => is_string($q) ? '' : ($q['answer'] ?? ''),
                ];
            }
            $data = array_merge($data, [
                'headline' => $contentData['title'],
                'faq_items' => $faqItems,
            ]);
        } elseif ($schemaType === 'Product') {
            $data = array_merge($data, [
                'name' => $contentData['title'],
                'description' => $contentData['description'] ?: $contentData['body'],
                'keyword' => $contentData['keyword'],
            ]);
            if ($businessProfile) {
                $data['brand'] = $businessProfile->business_name ?: $businessProfile->name;
                $data['seller_name'] = $businessProfile->business_name ?: '';
            }
        } elseif ($schemaType === 'BreadcrumbList') {
            $data = array_merge($data, [
                'headline' => $contentData['title'],
                'items' => [
                    ['name' => 'Home', 'url' => url('/')],
                    ['name' => $contentData['title'], 'url' => ''],
                ],
            ]);
        } elseif ($schemaType === 'LocalBusiness') {
            $data = array_merge($data, [
                'headline' => $contentData['title'],
                'description' => $contentData['description'] ?: $contentData['body'],
            ]);
            if ($businessProfile) {
                $bp = $businessProfile;
                $data['business_name'] = $bp->business_name ?: $bp->name;
                $data['description'] = $bp->description ?: $data['description'];
                $data['address'] = $bp->address ?: '';
                $data['telephone'] = $bp->contact_phone ?: '';
                $data['email'] = $bp->contact_email ?: '';
                $data['opening_hours'] = $bp->business_hours ?: '';
                $data['url'] = $bp->website_url ?: '';
                $data['same_as'] = $bp->social_media ? array_values(array_filter($bp->social_media)) : [];
            }
        } elseif ($schemaType === 'HowTo') {
            $data = array_merge($data, [
                'name' => $contentData['title'],
                'description' => $contentData['description'] ?: 'Langkah-langkah ' . $contentData['title'],
                'body' => $contentData['body'],
            ]);
        }

        return $data;
    }

    private function generateManually(string $type, array $data, ?string $targetUrl, ?object $sourceable): array
    {
        $base = ['@context' => 'https://schema.org'];

        $generated = match ($type) {
            'Article' => $this->buildArticle($base, $data, $targetUrl),
            'FAQPage' => $this->buildFaq($base, $data, $targetUrl),
            'Product' => $this->buildProduct($base, $data, $targetUrl),
            'LocalBusiness' => $this->buildLocalBusiness($base, $data, $targetUrl),
            'BreadcrumbList' => $this->buildBreadcrumb($base, $data, $targetUrl),
            'Review' => $this->buildReview($base, $data, $targetUrl),
            'Recipe' => $this->buildRecipe($base, $data, $targetUrl),
            'VideoObject' => $this->buildVideo($base, $data, $targetUrl),
            'HowTo' => $this->buildHowTo($base, $data, $targetUrl),
            'Event' => $this->buildEvent($base, $data, $targetUrl),
            default => $base + ['@type' => 'Thing', 'name' => $data['name'] ?? ''],
        };

        return $generated;
    }

    private function buildArticle(array $base, array $d, ?string $url): array
    {
        $schema = $base + [
            '@type' => 'Article',
            'headline' => $d['headline'] ?? $d['name'] ?? '',
            'description' => $d['description'] ?? '',
        ];
        if (!empty($d['article_body'])) {
            $schema['articleBody'] = $d['article_body'];
        }
        if (!empty($d['keyword'])) {
            $schema['keywords'] = $d['keyword'];
        }
        if (!empty($d['date_published'])) {
            $schema['datePublished'] = $d['date_published'];
        }
        if (!empty($d['date_modified'])) {
            $schema['dateModified'] = $d['date_modified'];
        }
        if (!empty($d['author_name'])) {
            $schema['author'] = [
                '@type' => $d['author_type'] ?? 'Person',
                'name' => $d['author_name'],
            ];
        }
        if ($url) {
            $schema['url'] = $url;
            $schema['mainEntityOfPage'] = $url;
        }
        if (!empty($d['image_url'])) {
            $schema['image'] = $d['image_url'];
        }
        if (!empty($d['publisher_name'])) {
            $schema['publisher'] = [
                '@type' => $d['publisher_type'] ?? 'Organization',
                'name' => $d['publisher_name'],
            ];
            if (!empty($d['publisher_logo'])) {
                $schema['publisher']['logo'] = [
                    '@type' => 'ImageObject',
                    'url' => $d['publisher_logo'],
                ];
            }
        }
        return $schema;
    }

    private function buildFaq(array $base, array $d, ?string $url): array
    {
        $items = [];
        foreach ($d['faq_items'] ?? [] as $item) {
            $q = is_string($item) ? $item : ($item['question'] ?? '');
            $a = is_array($item) ? ($item['answer'] ?? '') : '';
            if ($q) {
                $items[] = [
                    '@type' => 'Question',
                    'name' => $q,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $a ?: 'Lihat penjelasan lengkap di artikel terkait.',
                    ],
                ];
            }
        }
        $schema = $base + [
            '@type' => 'FAQPage',
            'mainEntity' => $items,
        ];
        if ($url) {
            $schema['url'] = $url;
        }
        return $schema;
    }

    private function buildProduct(array $base, array $d, ?string $url): array
    {
        $schema = $base + [
            '@type' => 'Product',
            'name' => $d['name'] ?? '',
            'description' => $d['description'] ?? '',
        ];
        if (!empty($d['brand'])) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $d['brand']];
        }
        if (!empty($d['image_url'])) {
            $schema['image'] = $d['image_url'];
        }
        if (!empty($d['sku'])) {
            $schema['sku'] = $d['sku'];
        }
        if (!empty($d['gtin'])) {
            $schema['gtin'] = $d['gtin'];
        }
        if (!empty($d['price']) || !empty($d['price_currency'])) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => $d['price'] ?? '0',
                'priceCurrency' => $d['price_currency'] ?? 'IDR',
                'availability' => $d['availability'] ?? 'https://schema.org/InStock',
                'url' => $url ?: '',
            ];
        }
        if (!empty($d['review_rating']) && !empty($d['review_count'])) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $d['review_rating'],
                'reviewCount' => (string) $d['review_count'],
            ];
        }
        if ($url) {
            $schema['url'] = $url;
        }
        return $schema;
    }

    private function buildLocalBusiness(array $base, array $d, ?string $url): array
    {
        $schema = $base + [
            '@type' => 'LocalBusiness',
            'name' => $d['business_name'] ?? $d['name'] ?? '',
            'description' => $d['description'] ?? '',
        ];
        if (!empty($d['address'])) {
            $schema['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $d['address']];
        }
        if (!empty($d['telephone'])) {
            $schema['telephone'] = $d['telephone'];
        }
        if (!empty($d['email'])) {
            $schema['email'] = $d['email'];
        }
        if (!empty($d['opening_hours'])) {
            $schema['openingHours'] = $d['opening_hours'];
        }
        if (!empty($d['image_url'])) {
            $schema['image'] = $d['image_url'];
        }
        if (!empty($d['same_as'])) {
            $schema['sameAs'] = is_array($d['same_as']) ? $d['same_as'] : [$d['same_as']];
        }
        if ($url ?: !empty($d['url'])) {
            $schema['url'] = $url ?: $d['url'];
        }
        return $schema;
    }

    private function buildBreadcrumb(array $base, array $d, ?string $url): array
    {
        $items = [];
        $position = 1;
        foreach ($d['items'] ?? [] as $item) {
            $itemUrl = is_string($item) ? '' : ($item['url'] ?? '');
            $itemName = is_string($item) ? $item : ($item['name'] ?? '');
            if ($itemName) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $itemName,
                    'item' => $itemUrl ?: ($url ?: ''),
                ];
            }
        }
        return $base + [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    private function buildReview(array $base, array $d, ?string $url): array
    {
        $schema = $base + [
            '@type' => 'Review',
            'name' => $d['name'] ?? '',
            'reviewBody' => $d['review_body'] ?? '',
        ];
        if (!empty($d['review_rating'])) {
            $schema['reviewRating'] = [
                '@type' => 'Rating',
                'ratingValue' => (string) $d['review_rating'],
                'bestRating' => $d['best_rating'] ?? '5',
            ];
        }
        if (!empty($d['author_name'])) {
            $schema['author'] = ['@type' => 'Person', 'name' => $d['author_name']];
        }
        if (!empty($d['item_reviewed_name'])) {
            $schema['itemReviewed'] = [
                '@type' => $d['item_reviewed_type'] ?? 'Product',
                'name' => $d['item_reviewed_name'],
            ];
        }
        if ($url) {
            $schema['url'] = $url;
        }
        return $schema;
    }

    private function buildRecipe(array $base, array $d, ?string $url): array
    {
        $schema = $base + [
            '@type' => 'Recipe',
            'name' => $d['name'] ?? '',
            'description' => $d['description'] ?? '',
            'recipeCategory' => $d['category'] ?? '',
            'recipeCuisine' => $d['cuisine'] ?? '',
        ];
        if (!empty($d['prep_time'])) {
            $schema['prepTime'] = $d['prep_time'];
        }
        if (!empty($d['cook_time'])) {
            $schema['cookTime'] = $d['cook_time'];
        }
        if (!empty($d['total_time'])) {
            $schema['totalTime'] = $d['total_time'];
        }
        if (!empty($d['recipe_yield'])) {
            $schema['recipeYield'] = $d['recipe_yield'];
        }
        if (!empty($d['ingredients'])) {
            $schema['recipeIngredient'] = is_array($d['ingredients']) ? $d['ingredients'] : explode("\n", $d['ingredients']);
        }
        if (!empty($d['instructions'])) {
            $steps = is_array($d['instructions']) ? $d['instructions'] : [$d['instructions']];
            $howToSteps = [];
            foreach ($steps as $i => $step) {
                $howToSteps[] = [
                    '@type' => 'HowToStep',
                    'position' => $i + 1,
                    'text' => is_string($step) ? $step : ($step['text'] ?? ''),
                ];
            }
            $schema['recipeInstructions'] = $howToSteps;
        }
        if (!empty($d['image_url'])) {
            $schema['image'] = $d['image_url'];
        }
        if (!empty($d['nutrition_calories'])) {
            $schema['nutrition'] = [
                '@type' => 'NutritionInformation',
                'calories' => $d['nutrition_calories'],
            ];
        }
        if ($url) {
            $schema['url'] = $url;
        }
        return $schema;
    }

    private function buildVideo(array $base, array $d, ?string $url): array
    {
        $schema = $base + [
            '@type' => 'VideoObject',
            'name' => $d['name'] ?? '',
            'description' => $d['description'] ?? '',
        ];
        if (!empty($d['thumbnail_url'])) {
            $schema['thumbnailUrl'] = $d['thumbnail_url'];
        }
        if (!empty($d['upload_date'])) {
            $schema['uploadDate'] = $d['upload_date'];
        }
        if (!empty($d['duration'])) {
            $schema['duration'] = $d['duration'];
        }
        if (!empty($d['embed_url'])) {
            $schema['embedUrl'] = $d['embed_url'];
        }
        if (!empty($d['content_url'])) {
            $schema['contentUrl'] = $d['content_url'];
        }
        if ($url) {
            $schema['url'] = $url;
        }
        return $schema;
    }

    private function buildHowTo(array $base, array $d, ?string $url): array
    {
        $schema = $base + [
            '@type' => 'HowTo',
            'name' => $d['name'] ?? '',
            'description' => $d['description'] ?? '',
        ];
        if (!empty($d['estimated_cost'])) {
            $schema['estimatedCost'] = [
                '@type' => 'MonetaryAmount',
                'value' => $d['estimated_cost'],
                'currency' => $d['cost_currency'] ?? 'IDR',
            ];
        }
        if (!empty($d['image_url'])) {
            $schema['image'] = $d['image_url'];
        }
        if (!empty($d['total_time'])) {
            $schema['totalTime'] = $d['total_time'];
        }
        if (!empty($d['tools'])) {
            $schema['tool'] = is_array($d['tools']) ? $d['tools'] : [$d['tools']];
        }
        if (!empty($d['supplies'])) {
            $schema['supply'] = is_array($d['supplies']) ? $d['supplies'] : [$d['supplies']];
        }
        if (!empty($d['steps'])) {
            $steps = is_array($d['steps']) ? $d['steps'] : [$d['steps']];
            $howToSteps = [];
            foreach ($steps as $i => $step) {
                $howToSteps[] = [
                    '@type' => 'HowToStep',
                    'position' => $i + 1,
                    'text' => is_string($step) ? $step : ($step['text'] ?? ''),
                ];
            }
            $schema['step'] = $howToSteps;
        }
        if ($url) {
            $schema['url'] = $url;
        }
        return $schema;
    }

    private function buildEvent(array $base, array $d, ?string $url): array
    {
        $schema = $base + [
            '@type' => 'Event',
            'name' => $d['name'] ?? '',
            'description' => $d['description'] ?? '',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => $d['attendance_mode'] ?? 'https://schema.org/OfflineEventAttendanceMode',
        ];
        if (!empty($d['start_date'])) {
            $schema['startDate'] = $d['start_date'];
        }
        if (!empty($d['end_date'])) {
            $schema['endDate'] = $d['end_date'];
        }
        if (!empty($d['location_name']) || !empty($d['location_address'])) {
            $schema['location'] = [
                '@type' => 'Place',
                'name' => $d['location_name'] ?? '',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $d['location_address'] ?? '',
                ],
            ];
        }
        if (!empty($d['image_url'])) {
            $schema['image'] = $d['image_url'];
        }
        if (!empty($d['performer'])) {
            $schema['performer'] = [
                '@type' => $d['performer_type'] ?? 'Person',
                'name' => $d['performer'],
            ];
        }
        if (!empty($d['organizer_name'])) {
            $schema['organizer'] = [
                '@type' => $d['organizer_type'] ?? 'Organization',
                'name' => $d['organizer_name'],
                'url' => $d['organizer_url'] ?? '',
            ];
        }
        if (!empty($d['offers_price'])) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'name' => $d['offers_label'] ?? 'Tiket Masuk',
                'price' => $d['offers_price'],
                'priceCurrency' => $d['offers_currency'] ?? 'IDR',
                'availability' => $d['offers_availability'] ?? 'https://schema.org/InStock',
                'url' => $url ?: '',
            ];
        }
        if ($url) {
            $schema['url'] = $url;
        }
        return $schema;
    }

    private function generateWithAi(string $type, array $data, ?string $targetUrl, ?object $sourceable): array
    {
        $prompt = $this->buildAiPrompt($type, $data, $targetUrl);
        $raw = $this->callAi($prompt);
        $parsed = json_decode($raw, true);

        if (!is_array($parsed) || empty($parsed)) {
            Log::warning('Schema AI: fallback to manual', ['type' => $type]);
            return $this->generateManually($type, $data, $targetUrl, $sourceable);
        }

        if (!isset($parsed['@context'])) {
            $parsed = ['@context' => 'https://schema.org'] + $parsed;
        }

        $this->validateSchema($parsed);
        return $parsed;
    }

    private function buildAiPrompt(string $type, array $data, ?string $targetUrl): string
    {
        $fields = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $typeLabel = SchemaMarkup::TYPES[$type] ?? $type;

        return <<<PROMPT
Kamu adalah ahli Schema.org markup. Buat JSON-LD valid untuk tipe **{$type}** ({$typeLabel}).

DATA YANG TERSEDIA:
{$fields}

Petunjuk:
1. Output HANYA JSON, tanpa markdown, tanpa komentar.
2. Gunakan @context "https://schema.org".
3. Isi semua field relevan berdasarkan data di atas.
4. Jika data tidak lengkap, gunakan nilai default yang masuk akal.
5. Pastikan valid dan bisa di-parse.

Output JSON:
PROMPT;
    }

    private function callAi(string $prompt): string
    {
        $key = config('services.nine_router.key')
            ?: config('services.openai.key')
            ?: config('services.anthropic.key')
            ?: env('AI_API_KEY')
            ?: '';

        $baseUrl = config('services.nine_router.base_url', 'https://api.9router.com/v1');
        $model = config('services.nine_router.model', 'deepseek-v4-flash-free');

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$key}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Kamu adalah asisten yang hanya merespons dengan JSON valid.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 2000,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content') ?: '{}';
            }

            Log::warning('Schema AI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Schema AI exception', ['error' => $e->getMessage()]);
        }

        return '{}';
    }

    private function validateSchema(array &$schema): void
    {
        if (!isset($schema['@type'])) {
            $schema['@type'] = 'Thing';
        }

        $removeEmpty = function (&$arr) use (&$removeEmpty) {
            foreach ($arr as $key => &$value) {
                if (is_array($value)) {
                    $removeEmpty($value);
                    if (empty($value)) {
                        unset($arr[$key]);
                    }
                } elseif (is_string($value) && trim($value) === '') {
                    unset($arr[$key]);
                } elseif ($value === null) {
                    unset($arr[$key]);
                }
            }
        };
        $removeEmpty($schema);
    }
}
