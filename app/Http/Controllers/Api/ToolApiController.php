<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteContentUrl;
use App\Models\Tools\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Modules\ContentGenerator\Jobs\ProcessContentBriefJob;
use Modules\ContentGenerator\Jobs\ProcessContentGenerationJob;
use Modules\ContentGenerator\Models\ContentBrief;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Services\ContentGeneratorService;
use Modules\KeywordResearch\Jobs\ProcessKeywordResearchJob;
use Modules\KeywordResearch\Models\KeywordResearch;
use Modules\SeoCluster\Jobs\ProcessClusterStructureJob;

class ToolApiController extends Controller
{
    private array $handlers = [
        'keyword-research' => [
            'research' => 'handleKeywordResearch',
            'status' => 'handleKeywordResearchStatus',
        ],
        'content-generator' => [
            'generate' => 'handleContentGenerate',
            'expand' => 'handleContentExpand',
            'status' => 'handleContentStatus',
            'generate-meta' => 'handleGenerateMeta',
            'regen-phase3' => 'handleContentRegenPhase3',
            'brief' => 'handleContentBrief',
            'brief-status' => 'handleContentBriefStatus',
            'sync-inventory' => 'handleSiteInventorySync',
            'wp-credentials' => 'handleWpCredentials',
            'sync-wp-url' => 'handleSyncWpUrl',
        ],
        'keyword-clusters' => [
            'list' => 'handleClusterList',
            'create' => 'handleClusterCreate',
            'show' => 'handleClusterShow',
            'activate' => 'handleClusterActivate',
            'pause' => 'handleClusterPause',
        ],
    ];

    public function execute(Request $request, string $tool, string $action): JsonResponse
    {
        $toolModel = Tool::where('slug', $tool)->where('is_active', true)->first();

        if (!$toolModel) {
            return response()->json(['success' => false, 'message' => 'Tool not found.'], 404);
        }

        if (!auth()->user()->hasToolAccess($tool)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this tool.'], 403);
        }

        $actions = $this->handlers[$tool] ?? null;

        if (!$actions || !isset($actions[$action])) {
            return response()->json(['success' => false, 'message' => "Action '{$action}' is not supported for '{$tool}'."], 400);
        }

        return $this->{$actions[$action]}($request);
    }

    private function handleKeywordResearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'lsi_count' => 'nullable|integer|min:3|max:50',
            'entities_count' => 'nullable|integer|min:1|max:30',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:255',
        ]);

        $website = $request->attributes->get('api_key_website');
        $service = app(ContentGeneratorService::class);
        $locale = $service->resolveLocale($validated['locale'] ?? null, $website, $validated['keyword']);

        $research = KeywordResearch::create([
            'user_id' => auth()->id(),
            'api_key_website_id' => $website?->id,
            'target_keyword' => $validated['keyword'],
            'locale' => $locale,
            'lsi_count' => $validated['lsi_count'] ?? 12,
            'entities_count' => $validated['entities_count'] ?? 7,
            'status' => 'pending',
            'source' => 'api',
            'webhook_url' => $validated['webhook_url'] ?? null,
            'webhook_secret' => $validated['webhook_secret'] ?? null,
        ]);

        ProcessKeywordResearchJob::dispatch($research);

        return response()->json([
            'success' => true,
            'message' => 'Keyword research queued successfully.',
            'data' => [
                'id' => $research->id,
                'target_keyword' => $research->target_keyword,
                'lsi_count' => $research->lsi_count,
                'entities_count' => $research->entities_count,
                'status' => 'pending',
            ],
        ], 202);
    }

    private function handleKeywordResearchStatus(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $research = KeywordResearch::where('user_id', auth()->id())
            ->findOrFail($request->id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $research->id,
                'target_keyword' => $research->target_keyword,
                'status' => $research->status,
                'lsi_keywords' => $research->lsi_keywords,
                'entities' => $research->entities,
                'created_at' => $research->created_at,
                'updated_at' => $research->updated_at,
            ],
        ]);
    }

    /**
     * Sinkronisasi inventaris URL + target keyword dari plugin WP.
     * Full-sync: item yang tidak ada di payload akan dihapus.
     */
    private function handleSiteInventorySync(Request $request): JsonResponse
    {
        $website = $request->attributes->get('api_key_website');

        $validated = $request->validate([
            'site_name' => 'nullable|string|max:191',
            'items' => 'present|array|max:1000',
            'items.*.url' => 'required|string|max:500',
            'items.*.title' => 'nullable|string|max:255',
            'items.*.keyword' => 'nullable|string|max:255',
        ]);

        if ($website && filled($validated['site_name'] ?? null)) {
            $website->update(['site_name' => trim((string) $validated['site_name'])]);
        }

        $userId = auth()->id();
        $keepUrls = [];

        foreach ($validated['items'] as $item) {
            $url = trim((string) ($item['url'] ?? ''));
            if ($url === '' || !str_starts_with($url, 'http')) {
                continue;
            }
            $keepUrls[] = $url;

            $keyword = trim((string) ($item['keyword'] ?? ''));
            SiteContentUrl::updateOrCreate(
                ['api_key_website_id' => $website?->id, 'url' => $url],
                [
                    'user_id' => $userId,
                    'title' => mb_substr(trim((string) ($item['title'] ?? '')), 0, 255) ?: $url,
                    'keyword' => $keyword !== '' ? mb_substr($keyword, 0, 255) : null,
                ]
            );
        }

        $stale = SiteContentUrl::where('user_id', $userId)
            ->forWebsite($website?->id)
            ->when(!empty($keepUrls), fn ($q) => $q->whereNotIn('url', $keepUrls));
        $removed = (clone $stale)->count();
        $stale->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inventaris situs tersinkron.',
            'data' => ['synced' => count($keepUrls), 'removed' => $removed],
        ]);
    }

    /**
     * Kredensial WordPress per-website (multi-tenant): dikirim otomatis oleh plugin
     * (Application Password dibuat programatis di sisi WP). Dipakai AutoClusterAgent
     * untuk publish artikel silo ke situs pemilik cluster.
     */
    private function handleWpCredentials(Request $request): JsonResponse
    {
        $website = $request->attributes->get('api_key_website');

        if (!$website) {
            return response()->json(['success' => false, 'message' => 'Domain situs belum terdaftar pada API key ini.'], 422);
        }

        $validated = $request->validate([
            'wp_url' => 'required|url|max:255',
            'wp_username' => 'required|string|max:100',
            'wp_app_password' => 'required|string|max:255',
        ]);

        $website->update([
            'wp_url' => rtrim(trim($validated['wp_url']), '/'),
            'wp_username' => trim($validated['wp_username']),
            'wp_app_password' => trim($validated['wp_app_password']),
        ]);

        return response()->json(['success' => true, 'message' => 'Kredensial WordPress situs tersimpan.']);
    }

    private function handleSyncWpUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'wp_url' => 'required|string|max:500',
        ]);

        $generation = ContentGeneration::where('user_id', auth()->id())
            ->findOrFail($validated['id']);

        $generation->update(['wp_url' => $validated['wp_url']]);

        return response()->json(['success' => true, 'message' => 'URL WordPress konten tersimpan.']);
    }

    private function handleContentBrief(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'keyword_count' => 'nullable|integer|min:8|max:12',
        ]);

        $website = $request->attributes->get('api_key_website');
        $service = app(ContentGeneratorService::class);
        $locale = $service->resolveLocale($validated['locale'] ?? null, $website, $validated['keyword']);

        $brief = ContentBrief::create([
            'user_id' => auth()->id(),
            'api_key_website_id' => $website?->id,
            'target_keyword' => $validated['keyword'],
            'locale' => $locale,
            'status' => 'pending',
        ]);

        ProcessContentBriefJob::dispatch($brief);

        return response()->json([
            'success' => true,
            'message' => 'Content brief queued successfully.',
            'data' => [
                'id' => $brief->id,
                'target_keyword' => $brief->target_keyword,
                'status' => 'pending',
            ],
        ], 202);
    }

    private function handleContentBriefStatus(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $brief = ContentBrief::where('user_id', auth()->id())
            ->findOrFail($request->id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $brief->id,
                'target_keyword' => $brief->target_keyword,
                'status' => $brief->status,
                'meta_title' => $brief->meta_title,
                'h1_tag' => $brief->h1_tag,
                'url_slug' => $brief->url_slug,
                'target_audience' => $brief->target_audience,
                'pain_point' => $brief->pain_point,
                'local_entities' => $brief->local_entities,
                'keywords' => $brief->keywords,
                'error_message' => $brief->error_message,
                'created_at' => $brief->created_at,
                'updated_at' => $brief->updated_at,
            ],
        ]);
    }

    private function handleContentGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'tone' => 'nullable|string|max:50',
            'content_type' => ['nullable', 'string', 'max:30', Rule::in(['post', 'page', 'product', 'product_cat', 'tag'])],
            'lsi_keywords' => 'nullable|array|max:50',
            'lsi_keywords.*' => 'nullable',
            'entities' => 'nullable|array|max:30',
            'entities.*' => 'nullable',
            'business_profile_id' => $this->businessProfileIdRule($request),
            'keyword_research_id' => $this->keywordResearchIdRule($request),
            'content_brief_id' => $this->contentBriefIdRule($request),
            'link_sources' => 'nullable|array|max:50',
            'link_sources.*' => 'nullable',
            'target_words' => 'nullable|integer|min:100|max:10000',
            'include_external_links' => 'nullable|boolean',
        ]);

        $website = $request->attributes->get('api_key_website');
        $service = app(ContentGeneratorService::class);
        $locale = $service->resolveLocale($validated['locale'] ?? null, $website, $validated['keyword']);

        $generation = ContentGeneration::create([
            'user_id' => auth()->id(),
            'api_key_website_id' => $website?->id,
            'target_keyword' => $validated['keyword'],
            'locale' => $locale,
            'tone' => $validated['tone'] ?? 'informative',
            'content_type' => $validated['content_type'] ?? 'post',
            'lsi_keywords' => $validated['lsi_keywords'] ?? [],
            'entities' => $validated['entities'] ?? [],
            'link_sources' => $validated['link_sources'] ?? [],
            'business_profile_id' => $validated['business_profile_id'] ?? null,
            'keyword_research_id' => $validated['keyword_research_id'] ?? null,
            'content_brief_id' => $validated['content_brief_id'] ?? null,
            'target_words' => $validated['target_words'] ?? null,
            'include_external_links' => $validated['include_external_links'] ?? null,
            'status' => 'draft',
            'current_phase' => 0,
        ]);

        ProcessContentGenerationJob::dispatch($generation);

        return response()->json([
            'success' => true,
            'message' => 'Content generation queued successfully.',
            'data' => [
                'id' => $generation->id,
                'target_keyword' => $generation->target_keyword,
                'status' => 'draft',
            ],
        ], 202);
    }

    private function handleContentExpand(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'keyword' => 'required|string|max:255',
            'locale' => 'nullable|string|max:10',
            'tone' => 'nullable|string|max:50',
            'content_type' => ['nullable', 'string', 'max:30', Rule::in(['post', 'page', 'product', 'product_cat', 'tag'])],
            'lsi_keywords' => 'nullable|array|max:50',
            'lsi_keywords.*' => 'nullable',
            'entities' => 'nullable|array|max:30',
            'entities.*' => 'nullable',
            'business_profile_id' => $this->businessProfileIdRule($request),
            'content_brief_id' => $this->contentBriefIdRule($request),
            'link_sources' => 'nullable|array|max:50',
            'link_sources.*' => 'nullable',
            'target_words' => 'nullable|integer|min:100|max:10000',
            'include_external_links' => 'nullable|boolean',
        ]);

        $website = $request->attributes->get('api_key_website');
        $service = app(ContentGeneratorService::class);
        $locale = $service->resolveLocale($validated['locale'] ?? null, $website, $validated['keyword']);

        $generation = ContentGeneration::create([
            'user_id' => auth()->id(),
            'api_key_website_id' => $website?->id,
            'target_keyword' => $validated['keyword'],
            'locale' => $locale,
            'tone' => $validated['tone'] ?? 'informative',
            'content_type' => $validated['content_type'] ?? 'post',
            'lsi_keywords' => $validated['lsi_keywords'] ?? [],
            'entities' => $validated['entities'] ?? [],
            'link_sources' => $validated['link_sources'] ?? [],
            'business_profile_id' => $validated['business_profile_id'] ?? null,
            'content_brief_id' => $validated['content_brief_id'] ?? null,
            'target_words' => $validated['target_words'] ?? null,
            'include_external_links' => $validated['include_external_links'] ?? null,
            'phase_1_content' => $validated['content'],
            'status' => 'draft',
            'current_phase' => 1,
        ]);

        ProcessContentGenerationJob::dispatch($generation);

        return response()->json([
            'success' => true,
            'message' => 'Content expansion queued successfully.',
            'data' => [
                'id' => $generation->id,
                'target_keyword' => $generation->target_keyword,
                'status' => 'draft',
            ],
        ], 202);
    }

    private function handleContentStatus(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $generation = ContentGeneration::where('user_id', auth()->id())
            ->findOrFail($request->id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $generation->id,
                'target_keyword' => $generation->target_keyword,
                'status' => $generation->status,
                'current_phase' => $generation->current_phase,
                'phase_1_content' => $generation->phase_1_content,
                'phase_2_questions' => $generation->phase_2_questions,
                'phase_3_content' => $generation->phase_3_content,
                'meta_title' => $generation->meta_title,
                'meta_description' => $generation->meta_description,
                'created_at' => $generation->created_at,
                'updated_at' => $generation->updated_at,
            ],
        ]);
    }

    private function handleContentRegenPhase3(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $generation = ContentGeneration::where('user_id', auth()->id())
            ->findOrFail($request->id);

        if (empty($generation->phase_1_content) || empty($generation->phase_2_questions)) {
            return response()->json(['success' => false, 'message' => 'Phase 1 & 2 must be completed first.'], 400);
        }

        $questions = is_string($generation->phase_2_questions)
            ? json_decode($generation->phase_2_questions, true) ?: []
            : ($generation->phase_2_questions ?? []);

        try {
            $businessProfile = null;
            if ($generation->business_profile_id) {
                $businessProfile = \App\Models\BusinessProfile::find($generation->business_profile_id);
            }

            $website = null;
            if ($generation->api_key_website_id) {
                $website = \App\Models\ApiKeyWebsite::find($generation->api_key_website_id);
            }

            $content = app(ContentGeneratorService::class)->generatePhase3(
                $generation->phase_1_content,
                $questions,
                $generation->target_keyword,
                $generation->locale ?? 'id',
                $generation->tone ?? 'informative',
                $generation->lsi_keywords ?? [],
                $generation->entities ?? [],
                $generation->target_words,
                null,
                $generation->link_sources ?? [],
                $businessProfile,
                $generation->include_external_links,
                $website
            );

            $generation->update([
                'phase_3_content' => $content,
                'meta_title' => null,
                'meta_description' => null,
                'status' => 'completed',
                'current_phase' => 3,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $generation->id,
                    'phase_3_content' => $content,
                    'current_phase' => 3,
                    'status' => 'completed',
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Regen phase 3 failed', [
                'id' => $generation->id, 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to regenerate phase 3.'], 500);
        }
    }

    private function handleGenerateMeta(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $generation = ContentGeneration::where('user_id', auth()->id())
            ->findOrFail($request->id);

        if (empty($generation->phase_3_content)) {
            return response()->json(['success' => false, 'message' => 'Phase 3 must be completed first.'], 400);
        }

        try {
            $meta = app(ContentGeneratorService::class)->generateMetaData(
                $generation->phase_3_content,
                $generation->target_keyword,
                $generation->locale ?? 'id'
            );

            $generation->update([
                'meta_title' => $meta['title'],
                'meta_description' => $meta['description'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $generation->id,
                    'meta_title' => $meta['title'],
                    'meta_description' => $meta['description'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Generate meta API failed', [
                'id' => $generation->id, 'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to generate meta.'], 500);
        }
    }

    /**
     * Cluster hanya milik situs yang membuatnya: scope per user + per website.
     * Tanpa ini, semua situs yang memakai satu akun saling melihat cluster-nya.
     */
    private function clusterScope(Request $request)
    {
        $website = $request->attributes->get('api_key_website');

        return \Modules\SeoCluster\Models\KeywordCluster::where('user_id', auth()->id())
            ->when($website, fn ($q) => $q->where('api_key_website_id', $website->id));
    }

    private function handleClusterList(Request $request): JsonResponse
    {
        $clusters = $this->clusterScope($request)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'parent_keyword' => $c->parent_keyword,
                'status' => $c->status,
                'schedule' => $c->schedule,
                'progress' => $c->progress(),
                'pillar_post_url' => $c->pillar_post_url,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $clusters]);
    }

    private function handleClusterCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
            'parent_count' => 'nullable|integer|min:1|max:10',
            'child_count' => 'nullable|integer|min:1|max:15',
            'url_template' => 'nullable|string|max:500',
            'publish_start' => 'nullable|date_format:Y-m-d',
            'publish_end' => 'nullable|date_format:Y-m-d|after_or_equal:publish_start',
            'tz_offset' => 'nullable|numeric|between:-12,14',
        ]);

        if (!empty($validated['publish_start']) && !empty($validated['publish_end'])) {
            $rangeDays = Carbon::parse($validated['publish_start'])->diffInDays(Carbon::parse($validated['publish_end']));
            if ($rangeDays > 730) {
                return response()->json(['success' => false, 'message' => 'Rentang tanggal maksimal 2 tahun.'], 422);
            }
        }

        // Pembuatan struktur memanggil AI (bisa > 30 dtk) — antrikan agar tidak
        // terkena max_execution_time web host; pola sama dengan riset keyword.
        $urlTemplate = str_contains($validated['url_template'] ?? '', '{slug}')
            ? $validated['url_template']
            : '';

        ProcessClusterStructureJob::dispatch([
            'user_id' => auth()->id(),
            'topic' => $validated['topic'],
            'parent_count' => (int) ($validated['parent_count'] ?? 4),
            'child_count' => (int) ($validated['child_count'] ?? 4),
            'url_template' => $urlTemplate,
            'publish_start' => $validated['publish_start'] ?? null,
            'publish_end' => $validated['publish_end'] ?? null,
            'tz_offset' => isset($validated['tz_offset']) ? (float) $validated['tz_offset'] : 7,
            'api_key_website_id' => $request->attributes->get('api_key_website')?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Struktur SILO diantrikan. Cluster akan muncul otomatis dalam beberapa menit.',
            'data' => [],
        ], 202);
    }

    private function handleClusterShow(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $cluster = $this->clusterScope($request)
            ->with('keywords:id,cluster_id,keyword,status,post_url,published_at,error_message,retry_count')
            ->findOrFail($request->id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $cluster->id,
                'name' => $cluster->name,
                'parent_keyword' => $cluster->parent_keyword,
                'description' => $cluster->description,
                'status' => $cluster->status,
                'schedule' => $cluster->schedule,
                'progress' => $cluster->progress(),
                'pillar_post_url' => $cluster->pillar_post_url,
                'url_template' => $cluster->url_template,
                'publish_start' => $cluster->publish_start,
                'publish_end' => $cluster->publish_end,
                'image_enabled' => $cluster->image_enabled,
                'keywords' => $cluster->keywords,
                'created_at' => $cluster->created_at?->toIso8601String(),
            ],
        ]);
    }

    private function handleClusterActivate(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $cluster = $this->clusterScope($request)->findOrFail($request->id);
        app(\Modules\SeoCluster\Services\ClusterService::class)->activateCluster($cluster->id);

        return response()->json(['success' => true, 'message' => 'Cluster diaktifkan.']);
    }

    private function handleClusterPause(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $cluster = $this->clusterScope($request)->findOrFail($request->id);
        app(\Modules\SeoCluster\Services\ClusterService::class)->pauseCluster($cluster->id);

        return response()->json(['success' => true, 'message' => 'Cluster dijeda.']);
    }

    private function businessProfileIdRule(Request $request)
    {
        $website = $request->attributes->get('api_key_website');

        return ['nullable', 'integer', Rule::exists('business_profiles', 'id')->where(function ($query) use ($website) {
            $query->where('user_id', auth()->id())
                ->where('is_active', true);

            if ($website) {
                $query->where('api_key_website_id', $website->id);
            }
        })];
    }

    private function keywordResearchIdRule(Request $request)
    {
        return ['nullable', 'integer', Rule::exists('keyword_researches', 'id')
            ->where('user_id', auth()->id())];
    }

    private function contentBriefIdRule(Request $request)
    {
        return ['nullable', 'integer', Rule::exists('content_briefs', 'id')
            ->where('user_id', auth()->id())];
    }
}
