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

        $this->stepPing($cluster, $keyword, $published['url']);

        $this->clusterService->updateKeywordStatus($keyword->id, 'published', [
            'post_url' => $published['url'],
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
            $existing = KeywordResearch::where('target_keyword', $keyword->keyword)
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

        $generation = ContentGeneration::create([
            'user_id' => $cluster->user_id,
            'target_keyword' => $keyword->keyword,
            'locale' => 'id',
            'tone' => 'informative',
            'keyword_research_id' => $researchId,
            'lsi_keywords' => $research->lsi_keywords ?? [],
            'entities' => $research->entities ?? [],
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
            $existingPosts = $this->wpService->getExistingPosts(100);
            $opportunities = $this->linkService->findLinkOpportunities($content, $existingPosts);
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

        $title = $generation->meta_title ?: $keyword->keyword;

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

        try {
            $published = $this->wpService->publishPost($title, $content, $meta);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->clusterService->logAutomation($cluster->id, 'publish', 'failed', $message, keywordId: $keyword->id);
            throw new Exception('Publish gagal: ' . $message);
        }

        if (empty($published['url'])) {
            throw new Exception('Publish gagal: tidak ada URL yang dikembalikan WordPress.');
        }

        $this->clusterService->logAutomation(
            $cluster->id,
            'publish',
            'completed',
            'Artikel terpublish: ' . ($published['url'] ?? '-'),
            (int) round((microtime(true) - $start) * 1000),
            $keyword->id,
        );

        return $published;
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

    protected function markClusterCompleted(KeywordCluster $cluster): void
    {
        $total = $cluster->total_keywords;
        $done = $cluster->published_count + $cluster->failed_count;

        if ($total > 0 && $done >= $total && $cluster->status === 'active') {
            $cluster->update(['status' => 'completed']);
            Log::info('AutoClusterAgent: cluster selesai', ['cluster' => $cluster->id]);
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
