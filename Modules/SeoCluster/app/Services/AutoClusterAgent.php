<?php

namespace Modules\SeoCluster\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\ContentGenerator\Models\ContentGeneration;
use Modules\ContentGenerator\Services\ContentGeneratorService;
use Modules\KeywordResearch\Models\KeywordResearch;
use Modules\KeywordResearch\Services\KeywordResearchService;
use Modules\SeoCluster\Models\ClusterKeyword;
use Modules\SeoCluster\Models\KeywordCluster;
use Modules\AgentConnector\Services\ContentAnalyzerService;

class AutoClusterAgent
{
    protected array $stats = [
        'clusters_scanned' => 0,
        'keywords_processed' => 0,
        'keywords_published' => 0,
        'keywords_failed' => 0,
        'skipped' => 0,
    ];

    public function __construct(
        protected ClusterService $clusterService,
        protected KeywordResearchService $researchService,
        protected ContentGeneratorService $contentService,
        protected ContentAnalyzerService $analyzerService,
        protected WordPressService $wpService,
        protected ImageService $imageService,
        protected InternalLinkService $linkService,
        protected PingService $pingService,
    ) {}

    public function runCycle(?int $clusterId = null, bool $force = false): array
    {
        $this->stats = array_fill_keys(array_keys($this->stats), 0);

        $query = KeywordCluster::query();

        if ($clusterId) {
            $query->where('id', $clusterId);
        } else {
            $query->active();
        }

        $clusters = $query->orderBy('id')->get();

        foreach ($clusters as $cluster) {
            $this->stats['clusters_scanned']++;

            if (!$force && $cluster->status !== 'active') {
                continue;
            }

            if (!$force) {
                if (!$this->clusterService->checkPostingHours()) {
                    Log::info('AutoClusterAgent: di luar jam posting, cluster dilewati', ['cluster' => $cluster->id]);
                    $this->stats['skipped']++;
                    continue;
                }

                if (!$this->clusterService->checkDailyQuota($cluster->id)) {
                    Log::info('AutoClusterAgent: kuota harian tercapai', ['cluster' => $cluster->id]);
                    $this->stats['skipped']++;
                    continue;
                }
            }

            $keyword = $this->clusterService->getNextPendingKeyword($cluster->id);

            if (!$keyword) {
                $this->markClusterCompleted($cluster);
                continue;
            }

            try {
                $this->processKeyword($cluster, $keyword);
                $this->stats['keywords_processed']++;
            } catch (Exception $e) {
                Log::error('AutoClusterAgent: proses keyword gagal', [
                    'cluster' => $cluster->id,
                    'keyword' => $keyword->id,
                    'error' => $e->getMessage(),
                ]);

                $retryCount = ((int) $keyword->retry_count) + 1;
                $maxRetries = (int) config('seo-cluster.automation.max_retries', 3);

                if ($retryCount < $maxRetries) {
                    ClusterKeyword::where('id', $keyword->id)->update([
                        'status' => 'pending',
                        'retry_count' => $retryCount,
                        'error_message' => mb_substr($e->getMessage(), 0, 500),
                    ]);

                    $this->clusterService->logAutomation(
                        $cluster->id,
                        'error',
                        'failed',
                        "Gagal (percobaan {$retryCount}/{$maxRetries}): " . mb_substr($e->getMessage(), 0, 300),
                        keywordId: $keyword->id,
                    );

                    $this->stats['skipped']++;
                } else {
                    $this->clusterService->updateKeywordStatus($keyword->id, 'failed', [
                        'error_message' => mb_substr($e->getMessage(), 0, 500),
                    ]);
                    $this->clusterService->logAutomation(
                        $cluster->id,
                        'error',
                        'failed',
                        mb_substr($e->getMessage(), 0, 500),
                        keywordId: $keyword->id,
                    );

                    $this->stats['keywords_failed']++;
                    $this->checkAutoPause($cluster);
                }
            }
        }

        Log::info('AutoClusterAgent: siklus selesai', $this->stats);

        return $this->stats;
    }

    protected function processKeyword(KeywordCluster $cluster, ClusterKeyword $keyword): void
    {
        $started = microtime(true);

        $this->clusterService->updateKeywordStatus($keyword->id, 'researching');

        $researchId = $this->stepResearch($cluster, $keyword);
        $this->clusterService->updateKeywordStatus($keyword->id, 'researched', [
            'keyword_research_id' => $researchId,
        ]);

        $generationId = $this->stepGenerate($cluster, $keyword, $researchId);
        $this->clusterService->updateKeywordStatus($keyword->id, 'content_generated', [
            'content_generation_id' => $generationId,
        ]);

        $content = $this->stepQualityCheck($cluster, $keyword, $generationId);

        $content = $this->stepImages($cluster, $keyword, $content);

        $content = $this->stepInternalLinks($cluster, $keyword, $content);

        $published = $this->stepPublish($cluster, $keyword, $generationId, $content);

        // Artikel berjadwal (future) belum live — jangan di-ping
        if (($published['status'] ?? '') !== 'future') {
            $this->stepPing($cluster, $keyword, $published['url']);
        }

        $this->clusterService->updateKeywordStatus($keyword->id, 'published', [
            'post_url' => $published['url'],
            'wp_post_id' => $published['id'] ?? null,
        ]);

        $this->recordAnalytics($cluster, $keyword, $generationId, $published, $started);

        Log::info('AutoClusterAgent: keyword selesai diproses', [
            'cluster' => $cluster->id,
            'keyword' => $keyword->id,
            'url' => $published['url'],
        ]);
    }

    protected function stepResearch(KeywordCluster $cluster, ClusterKeyword $keyword): int
    {
        $this->clusterService->logAutomation($cluster->id, 'research', 'started', "Riset keyword '{$keyword->keyword}'", keywordId: $keyword->id);
        $start = microtime(true);

        try {
            $existing = KeywordResearch::where('user_id', $cluster->user_id)
                ->where('target_keyword', $keyword->keyword)
                ->where('status', 'completed')
                ->latest()
                ->first();

            if ($existing) {
                $research = $existing;
            } else {
                $result = $this->researchService->research($keyword->keyword, 'id');

                $research = KeywordResearch::create([
                    'user_id' => $cluster->user_id,
                    'target_keyword' => $keyword->keyword,
                    'locale' => 'id',
                    'lsi_count' => count($result['lsi_keywords'] ?? []),
                    'entities_count' => count($result['entities'] ?? []),
                    'lsi_keywords' => $result['lsi_keywords'] ?? [],
                    'entities' => $result['entities'] ?? [],
                    'status' => 'completed',
                    'source' => 'seo-cluster',
                    'tokens_in' => $this->researchService->tokenUsage['tokens_in'],
                    'tokens_out' => $this->researchService->tokenUsage['tokens_out'],
                    'tokens_total' => $this->researchService->tokenUsage['tokens_in'] + $this->researchService->tokenUsage['tokens_out'],
                ]);
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->clusterService->logAutomation($cluster->id, 'research', 'failed', $message, keywordId: $keyword->id);
            throw new Exception('Riset gagal: ' . $message);
        }

        $this->clusterService->logAutomation(
            $cluster->id,
            'research',
            'completed',
            "Riset selesai: " . count($research->lsi_keywords ?? []) . " LSI, " . count($research->entities ?? []) . " entities",
            (int) round((microtime(true) - $start) * 1000),
            $keyword->id,
        );

        return $research->id;
    }

    protected function stepGenerate(KeywordCluster $cluster, ClusterKeyword $keyword, int $researchId): int
    {
        $this->clusterService->logAutomation($cluster->id, 'generate', 'started', "Generate konten '{$keyword->keyword}'", keywordId: $keyword->id);
        $start = microtime(true);

        $research = KeywordResearch::find($researchId);
        $linkSources = $this->getSiloLinkSources($cluster, $keyword->id);

        $generation = ContentGeneration::create([
            'user_id' => $cluster->user_id,
            'target_keyword' => $keyword->keyword,
            'locale' => 'id',
            'tone' => 'informative',
            'content_type' => 'post',
            'keyword_research_id' => $researchId,
            'lsi_keywords' => $research->lsi_keywords ?? [],
            'entities' => $research->entities ?? [],
            'link_sources' => $linkSources,
            'status' => 'draft',
            'current_phase' => 0,
        ]);

        try {
            $phase1 = $this->contentService->generatePhase1(
                $keyword->keyword,
                'id',
                'informative',
                $research->lsi_keywords ?? [],
                $research->entities ?? [],
                $cluster->user_id,
            );
            $generation->update(['phase_1_content' => $phase1, 'current_phase' => 1, 'status' => 'phase_1']);

            $questions = $this->contentService->generatePhase2($phase1, $keyword->keyword);
            $generation->update(['phase_2_questions' => $questions, 'current_phase' => 2, 'status' => 'phase_2']);

            $final = $this->contentService->generatePhase3(
                $phase1,
                $questions,
                $keyword->keyword,
                'id',
                'informative',
                $research->lsi_keywords ?? [],
                $research->entities ?? [],
                null,
                null,
                $linkSources
            );
            $generation->update(['phase_3_content' => $final, 'current_phase' => 3, 'status' => 'phase_3']);

            try {
                $meta = $this->contentService->generateMetaData($final, $keyword->keyword, 'id');
                $generation->update([
                    'meta_title' => $meta['title'],
                    'meta_description' => $meta['description'],
                ]);
            } catch (Exception $e) {
                Log::warning('AutoClusterAgent: meta generation gagal, dilewati', ['error' => $e->getMessage()]);
            }
        } catch (Exception $e) {
            $generation->update(['status' => 'failed']);
            throw new Exception('Generate konten gagal: ' . $e->getMessage());
        }

        $generation->update([
            'status' => 'completed',
            'tokens_in' => $this->contentService->tokenUsage['tokens_in'],
            'tokens_out' => $this->contentService->tokenUsage['tokens_out'],
            'tokens_total' => $this->contentService->tokenUsage['tokens_in'] + $this->contentService->tokenUsage['tokens_out'],
        ]);

        $this->clusterService->logAutomation(
            $cluster->id,
            'generate',
            'completed',
            "Konten selesai (ID {$generation->id})",
            (int) round((microtime(true) - $start) * 1000),
            $keyword->id,
        );

        return $generation->id;
    }

    protected function stepQualityCheck(KeywordCluster $cluster, ClusterKeyword $keyword, int $generationId): string
    {
        $this->clusterService->logAutomation($cluster->id, 'quality_check', 'started', "Cek kualitas konten ID {$generationId}", keywordId: $keyword->id);
        $start = microtime(true);

        $generation = ContentGeneration::findOrFail($generationId);
        $content = $generation->phase_3_content ?? '';
        $minReadability = (int) config('seo-cluster.automation.min_readability', 50);

        $result = $this->analyzerService->analyze($content, $keyword->keyword);
        $readability = (float) ($result['readability_score'] ?? $result['details']['readability_score'] ?? 0);

        $this->clusterService->recordKeywordAnalytic($keyword->id, [
            'quality_score' => $readability,
            'word_count' => $result['details']['total_words'] ?? null,
        ]);

        if ($readability < $minReadability) {
            $this->clusterService->logAutomation($cluster->id, 'quality_check', 'completed', "Skor readability {$readability} < {$minReadability}, regenerate 1x", keywordId: $keyword->id);

            try {
                $research = KeywordResearch::find($generation->keyword_research_id);

                $regenerated = $this->contentService->generatePhase3(
                    $generation->phase_1_content ?? '',
                    $generation->phase_2_questions ?? [],
                    $keyword->keyword,
                    'id',
                    'informative',
                    $research->lsi_keywords ?? [],
                    $research->entities ?? [],
                    null,
                    null,
                    $generation->link_sources ?? []
                );
                $generation->update(['phase_3_content' => $regenerated]);

                $content = $regenerated;

                $result = $this->analyzerService->analyze($content, $keyword->keyword);
                $readability = (float) ($result['readability_score'] ?? $result['details']['readability_score'] ?? 0);

                $this->clusterService->recordKeywordAnalytic($keyword->id, [
                    'quality_score' => $readability,
                    'word_count' => $result['details']['total_words'] ?? null,
                ]);
            } catch (Exception $e) {
                Log::warning('AutoClusterAgent: regenerate gagal', ['error' => $e->getMessage()]);
            }

            if ($readability < $minReadability) {
                throw new Exception("Kualitas konten rendah (readability {$readability} < {$minReadability})");
            }
        }

        $this->clusterService->logAutomation(
            $cluster->id,
            'quality_check',
            'completed',
            "Readability {$readability}, lolos kualitas",
            (int) round((microtime(true) - $start) * 1000),
            $keyword->id,
        );

        return $content;
    }

    protected function stepImages(KeywordCluster $cluster, ClusterKeyword $keyword, string $content): string
    {
        if (!$cluster->image_enabled) {
            return $content;
        }

        $this->clusterService->logAutomation($cluster->id, 'image', 'started', 'Cari & upload gambar', keywordId: $keyword->id);
        $start = microtime(true);

        $imageKeyword = $cluster->image_keyword ?: $keyword->keyword;
        $count = max(1, (int) $cluster->image_per_article);
        $quality = (int) $cluster->webp_quality;
        $source = $cluster->image_source ?: 'duckduckgo';

        $images = $this->imageService->fetchAndUpload($imageKeyword, $this->wpService, $count, $source);

        if (empty($images)) {
            $this->clusterService->logAutomation($cluster->id, 'image', 'completed', 'Tidak ada gambar yang berhasil diupload', (int) round((microtime(true) - $start) * 1000), $keyword->id);
            return $content;
        }

        $content = $this->imageService->injectImages($content, $images, $imageKeyword);

        $this->clusterService->recordKeywordAnalytic($keyword->id, [
            'image_count' => count($images),
        ]);

        $this->clusterService->logAutomation(
            $cluster->id,
            'image',
            'completed',
            count($images) . ' gambar diupload',
            (int) round((microtime(true) - $start) * 1000),
            $keyword->id,
        );

        return $content;
    }

    protected function stepInternalLinks(KeywordCluster $cluster, ClusterKeyword $keyword, string $content): string
    {
        $this->clusterService->logAutomation($cluster->id, 'link', 'started', 'Cari peluang internal link', keywordId: $keyword->id);
        $start = microtime(true);

        try {
            $siloPosts = collect($this->getSiloLinkSources($cluster, $keyword->id))
                ->filter(fn ($s) => !empty($s['url']))
                ->map(fn ($s) => ['title' => $s['title'], 'url' => $s['url']])
                ->values()
                ->all();

            $opportunities = $this->linkService->findLinkOpportunities($content, $siloPosts);
            $content = $this->linkService->injectLinks($content, $opportunities, 2);

            $this->clusterService->logAutomation(
                $cluster->id,
                'link',
                'completed',
                count($opportunities) . ' internal link disisipkan',
                (int) round((microtime(true) - $start) * 1000),
                $keyword->id,
            );
        } catch (Exception $e) {
            Log::warning('AutoClusterAgent: internal link gagal, dilewati', ['error' => $e->getMessage()]);
            $this->clusterService->logAutomation($cluster->id, 'link', 'failed', 'Internal link dilewati: ' . $e->getMessage(), keywordId: $keyword->id);
        }

        return $content;
    }

    protected function stepPublish(KeywordCluster $cluster, ClusterKeyword $keyword, int $generationId, string $content): array
    {
        $this->clusterService->logAutomation($cluster->id, 'publish', 'started', "Publish '{$keyword->keyword}' ke WordPress", keywordId: $keyword->id);
        $start = microtime(true);

        $generation = ContentGeneration::find($generationId);

        $title = \App\Support\SeoText::capTitle(
            $generation->meta_title ?: $keyword->keyword,
            70,
            $keyword->keyword
        );

        $meta = [
            'slug' => $this->wpService->createSlug($keyword->keyword),
            'excerpt' => $generation->meta_description ?? '',
        ];

        try {
            $categoryId = $this->wpService->findOrCreateCategory($cluster->parent_keyword);
            if ($categoryId) {
                $meta['categories'] = [$categoryId];
            }
        } catch (Exception $e) {
            Log::warning('AutoClusterAgent: kategori tidak dibuat', ['error' => $e->getMessage()]);
        }

        // Jadwal terbit: sebar merata dalam rentang tanggal, jam acak jam aktif (08:00-21:59)
        $siblings = $cluster->keywords()->orderBy('id')->pluck('id')->values();
        $slotIndex = max(0, (int) $siblings->search($keyword->id));
        $scheduledAt = $this->scheduleSlotFor($cluster, $slotIndex, max(1, $siblings->count()));

        try {
            $published = $this->wpService->publishPost($title, $content, $meta, $scheduledAt);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->clusterService->logAutomation($cluster->id, 'publish', 'failed', $message, keywordId: $keyword->id);
            throw new Exception('Publish gagal: ' . $message);
        }

        if (empty($published['url'])) {
            throw new Exception('Publish gagal: tidak ada URL yang dikembalikan WordPress.');
        }

        $isScheduled = ($published['status'] ?? '') === 'future';
        $this->clusterService->logAutomation(
            $cluster->id,
            'publish',
            'completed',
            ($isScheduled ? 'Artikel dijadwalkan (' . $scheduledAt . '): ' : 'Artikel terpublish: ') . ($published['url'] ?? '-'),
            (int) round((microtime(true) - $start) * 1000),
            $keyword->id,
        );

        return $published;
    }

    /**
     * Tanggal terbit untuk slot ke-i dari n artikel: disebar merata
     * antara publish_start dan publish_end (boleh tanggal lampau).
     * Jam diacak pada rentang aktif pembaca Indonesia (08:00-21:59),
     * mengikuti timezone situs via tz_offset.
     */
    protected function scheduleSlotFor(KeywordCluster $cluster, int $slotIndex, int $slotTotal): ?string
    {
        if (!$cluster->publish_start || !$cluster->publish_end) {
            return null;
        }

        $tz = sprintf('+%02d:00', (float) $cluster->tz_offset);
        $start = \Illuminate\Support\Carbon::parse($cluster->publish_start, $tz)->startOfDay();
        $end = \Illuminate\Support\Carbon::parse($cluster->publish_end, $tz)->startOfDay();

        if ($end->lt($start)) {
            return null;
        }

        $totalDays = (int) $start->diffInDays($end);
        $dayOffset = $slotTotal <= 1
            ? 0
            : (int) floor($slotIndex * ($totalDays + 1) / $slotTotal);

        $date = $start->copy()->addDays(min($dayOffset, $totalDays));

        return $date
            ->setTime(random_int(8, 21), random_int(0, 59), random_int(0, 59))
            ->format('Y-m-d H:i:s');
    }

    protected function stepPing(KeywordCluster $cluster, ClusterKeyword $keyword, string $postUrl): void
    {
        if (!$postUrl) {
            return;
        }

        $this->clusterService->logAutomation($cluster->id, 'ping', 'started', "Ping search engine: {$postUrl}", keywordId: $keyword->id);
        $start = microtime(true);

        $results = $this->pingService->pingAll($postUrl);

        $this->clusterService->logAutomation(
            $cluster->id,
            'ping',
            'completed',
            'Ping selesai: ' . json_encode($results),
            (int) round((microtime(true) - $start) * 1000),
            $keyword->id,
        );
    }

    protected function recordAnalytics(KeywordCluster $cluster, ClusterKeyword $keyword, int $generationId, array $published, float $started): void
    {
        $durationMinutes = round((microtime(true) - $started) / 60, 2);

        $generation = ContentGeneration::find($generationId);

        $this->clusterService->recordKeywordAnalytic($keyword->id, [
            'post_url' => $published['url'] ?? null,
            'published_at' => now(),
            'posted_hour' => (int) now()->format('G'),
            'tokens_used' => $generation->tokens_total ?? 0,
        ]);

        $cluster->refresh();

        $this->clusterService->recordAnalytic($cluster->id, [
            'keywords_processed' => $cluster->keywords()->whereIn('status', ['published', 'failed'])->count(),
            'keywords_published' => $cluster->published_count,
            'keywords_failed' => $cluster->failed_count,
            'avg_duration_minutes' => $durationMinutes,
        ]);

        $this->stats['keywords_published']++;
    }

    protected function getSiloLinkSources(KeywordCluster $cluster, ?int $excludeKeywordId = null): array
    {
        $baseUrl = rtrim($this->wpService->baseUrl(), '/');
        // url_template NULL = dibuat tanpa info permalink (fallback config);
        // string kosong = situs WP permalink Plain -> jangan prediksi URL
        $pattern = $cluster->url_template !== null
            ? (string) $cluster->url_template
            : (string) config('seo-cluster.silo.url_pattern', '{url}/{slug}/');
        $canPredict = $pattern !== '' && str_contains($pattern, '{slug}');

        $sources = [];

        if (!empty($cluster->pillar_post_url)) {
            $sources[] = [
                'title' => $cluster->parent_keyword,
                'url' => $cluster->pillar_post_url,
                'keyword' => $cluster->parent_keyword,
            ];
        }

        foreach ($cluster->keywords as $kw) {
            if ($excludeKeywordId && (int) $kw->id === (int) $excludeKeywordId) {
                continue;
            }

            $url = $kw->post_url;
            if (!$url && $canPredict) {
                $url = str_replace(['{url}', '{slug}'], [$baseUrl, \App\Support\SeoText::slugify($kw->keyword)], $pattern);
            }
            if (!$url) {
                continue;
            }

            $sources[] = [
                'title' => $kw->keyword,
                'url' => $url,
                'keyword' => $kw->keyword,
            ];
        }

        return $sources;
    }

    protected function markClusterCompleted(KeywordCluster $cluster): void
    {
        $total = $cluster->total_keywords;
        $done = $cluster->published_count + $cluster->failed_count;

        if ($total > 0 && $done >= $total && $cluster->status === 'active') {
            $cluster->update(['status' => 'completed']);
            Log::info('AutoClusterAgent: cluster selesai', ['cluster' => $cluster->id]);

            if (empty($cluster->pillar_post_url)) {
                try {
                    $this->generatePillar($cluster);
                } catch (Exception $e) {
                    Log::error('AutoClusterAgent: generate pillar gagal', [
                        'cluster' => $cluster->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->clusterService->logAutomation(
                        $cluster->id,
                        'pillar',
                        'failed',
                        mb_substr($e->getMessage(), 0, 500),
                    );
                }
            }
        }
    }

    protected function generatePillar(KeywordCluster $cluster): void
    {
        $children = ClusterKeyword::where('cluster_id', $cluster->id)
            ->where('status', 'published')
            ->whereNotNull('post_url')
            ->orderBy('id')
            ->get();

        if ($children->isEmpty()) {
            $this->clusterService->logAutomation($cluster->id, 'pillar', 'skipped', 'Tidak ada child terpublish, pillar dilewati');
            return;
        }

        $this->clusterService->logAutomation($cluster->id, 'pillar', 'started', "Generate artikel pillar '{$cluster->parent_keyword}'");
        $start = microtime(true);

        $linkSources = $children
            ->map(fn ($c) => ['title' => $c->keyword, 'url' => $c->post_url, 'keyword' => $c->keyword])
            ->values()
            ->all();

        $targetWords = max(800, (int) config('seo-cluster.silo.pillar_target_words', 2000));

        $generation = ContentGeneration::create([
            'user_id' => $cluster->user_id,
            'target_keyword' => $cluster->parent_keyword,
            'locale' => 'id',
            'tone' => 'informative',
            'content_type' => 'post',
            'lsi_keywords' => [],
            'entities' => [],
            'link_sources' => $linkSources,
            'target_words' => $targetWords,
            'status' => 'draft',
            'current_phase' => 0,
        ]);

        try {
            $phase1 = $this->contentService->generatePhase1(
                $cluster->parent_keyword . ' panduan lengkap',
                'id',
                'informative',
                [],
                [],
                $cluster->user_id,
                null,
                $targetWords,
            );
            $generation->update(['phase_1_content' => $phase1, 'current_phase' => 1, 'status' => 'phase_1']);

            $questions = $this->contentService->generatePhase2($phase1, $cluster->parent_keyword);
            $generation->update(['phase_2_questions' => $questions, 'current_phase' => 2, 'status' => 'phase_2']);

            $final = $this->contentService->generatePhase3(
                $phase1,
                $questions,
                $cluster->parent_keyword,
                'id',
                'informative',
                [],
                [],
                $targetWords,
                null,
                $linkSources
            );

            if (trim((string) $final) === '') {
                throw new Exception('Konten pillar kosong');
            }

            $generation->update(['phase_3_content' => $final, 'current_phase' => 3, 'status' => 'phase_3']);
        } catch (Exception $e) {
            $generation->update(['status' => 'failed']);
            throw new Exception('Generate konten pillar gagal: ' . $e->getMessage());
        }

        try {
            $meta = $this->contentService->generateMetaData($final, $cluster->parent_keyword, 'id');
            $generation->update([
                'meta_title' => $meta['title'],
                'meta_description' => $meta['description'],
            ]);
        } catch (Exception $e) {
            Log::warning('AutoClusterAgent: meta pillar gagal, dilewati', ['error' => $e->getMessage()]);
        }

        $title = \App\Support\SeoText::capTitle(
            $generation->meta_title ?: $cluster->parent_keyword,
            70,
            $cluster->parent_keyword
        );

        $metaData = [
            'slug' => $this->wpService->createSlug($cluster->parent_keyword),
            'excerpt' => $generation->meta_description ?? '',
        ];

        try {
            $categoryId = $this->wpService->findOrCreateCategory($cluster->parent_keyword);
            if ($categoryId) {
                $metaData['categories'] = [$categoryId];
            }
        } catch (Exception $e) {
            Log::warning('AutoClusterAgent: kategori pillar tidak dibuat', ['error' => $e->getMessage()]);
        }

        // Pillar terbit di akhir rentang jadwal (setelah semua child), jam acak jam aktif
        $pillarWhen = null;
        if ($cluster->publish_start && $cluster->publish_end) {
            $tz = sprintf('+%02d:00', (float) $cluster->tz_offset);
            $pillarWhen = \Illuminate\Support\Carbon::parse($cluster->publish_end, $tz)
                ->endOfDay()
                ->subHours(random_int(2, 10))
                ->format('Y-m-d H:i:s');
        }

        $published = $this->wpService->publishPost($title, $final, $metaData, $pillarWhen);

        if (empty($published['url'])) {
            throw new Exception('Publish pillar gagal: tidak ada URL dari WordPress.');
        }

        $generation->update([
            'status' => 'completed',
            'tokens_in' => $this->contentService->tokenUsage['tokens_in'],
            'tokens_out' => $this->contentService->tokenUsage['tokens_out'],
            'tokens_total' => $this->contentService->tokenUsage['tokens_in'] + $this->contentService->tokenUsage['tokens_out'],
        ]);

        $cluster->update([
            'pillar_post_url' => $published['url'],
            'pillar_generation_id' => $generation->id,
        ]);

        if (($published['status'] ?? '') !== 'future') {
            $this->pingService->pingAll($published['url']);
        }

        $this->clusterService->logAutomation(
            $cluster->id,
            'pillar',
            'completed',
            (($published['status'] ?? '') === 'future' ? 'Pillar dijadwalkan (' . $pillarWhen . '): ' : 'Pillar terpublish: ') . $published['url'],
            (int) round((microtime(true) - $start) * 1000),
        );

        $this->backFillPillarLinks($cluster, $children);
    }

    protected function backFillPillarLinks(KeywordCluster $cluster, $children): void
    {
        if (empty($cluster->pillar_post_url)) {
            return;
        }

        foreach ($children as $child) {
            try {
                if (!$child->wp_post_id) {
                    continue;
                }

                $post = $this->wpService->getPostContent((int) $child->wp_post_id);
                $updated = $this->linkService->injectSingleLink(
                    $post['content'] ?? '',
                    $cluster->parent_keyword,
                    $cluster->pillar_post_url,
                    $cluster->parent_keyword
                );

                if ($updated !== ($post['content'] ?? '')) {
                    $this->wpService->updatePost((int) $child->wp_post_id, ['content' => $updated]);
                    $this->clusterService->logAutomation($cluster->id, 'pillar', 'completed', "Back-link pillar ke '{$child->keyword}'");
                }
            } catch (Exception $e) {
                Log::warning('AutoClusterAgent: back-fill link pillar gagal', [
                    'cluster' => $cluster->id,
                    'keyword' => $child->keyword,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function checkAutoPause(KeywordCluster $cluster): void
    {
        $consecutiveFailures = ClusterKeyword::where('cluster_id', $cluster->id)
            ->where('status', 'failed')
            ->orderBy('id', 'desc')
            ->take(3)
            ->count();

        if ($consecutiveFailures >= 3) {
            $cluster->update(['status' => 'paused']);
            Log::warning('AutoClusterAgent: cluster auto-pause setelah 3 kegagalan beruntun', ['cluster' => $cluster->id]);
        }
    }
}
