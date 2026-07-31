<?php

namespace Modules\SeoCluster\Services;

use Illuminate\Support\Facades\DB;
use Modules\SeoCluster\Models\KeywordCluster;
use Modules\SeoCluster\Models\ClusterKeyword;
use Modules\SeoCluster\Models\ClusterAutomationLog;
use Modules\SeoCluster\Models\ClusterAnalytic;
use Modules\SeoCluster\Models\ClusterKeywordAnalytic;

class ClusterService
{
    public function createCluster(
        int $userId,
        string $name,
        string $parentKeyword,
        array $keywords,
        ?string $description = null,
        string $schedule = 'manual',
        ?string $imageKeyword = null,
        string $imageSource = 'duckduckgo',
        int $imagePerArticle = 3,
        int $webpQuality = 80,
    ): KeywordCluster {
        return DB::transaction(function () use ($userId, $name, $parentKeyword, $keywords, $description, $schedule, $imageKeyword, $imageSource, $imagePerArticle, $webpQuality) {
            $cluster = KeywordCluster::create([
                'user_id' => $userId,
                'name' => $name,
                'parent_keyword' => $parentKeyword,
                'description' => $description,
                'status' => 'draft',
                'schedule' => $schedule,
                'total_keywords' => count($keywords),
                'image_keyword' => $imageKeyword ?? $parentKeyword,
                'image_source' => $imageSource,
                'image_enabled' => true,
                'image_per_article' => $imagePerArticle,
                'webp_quality' => $webpQuality,
            ]);

            foreach ($keywords as $i => $keyword) {
                $cluster->keywords()->create([
                    'keyword' => trim($keyword),
                    'status' => 'pending',
                    'priority' => $i,
                ]);
            }

            return $cluster;
        });
    }

    public function addKeyword(int $clusterId, string $keyword, int $priority = 0): ClusterKeyword
    {
        $cluster = KeywordCluster::findOrFail($clusterId);

        $kw = $cluster->keywords()->create([
            'keyword' => trim($keyword),
            'status' => 'pending',
            'priority' => $priority,
        ]);

        $cluster->increment('total_keywords');

        return $kw;
    }

    public function removeKeyword(int $keywordId): bool
    {
        $keyword = ClusterKeyword::findOrFail($keywordId);
        $clusterId = $keyword->cluster_id;

        $deleted = $keyword->delete();

        if ($deleted) {
            KeywordCluster::where('id', $clusterId)->decrement('total_keywords');
        }

        return $deleted;
    }

    public function getNextPendingKeyword(int $clusterId): ?ClusterKeyword
    {
        return ClusterKeyword::where('cluster_id', $clusterId)
            ->where('status', 'pending')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }

    public function updateKeywordStatus(int $id, string $status, array $data = []): void
    {
        $update = array_merge(['status' => $status], $data);

        if ($status === 'published') {
            $update['published_at'] = now();
        }

        ClusterKeyword::where('id', $id)->update($update);

        if ($status === 'published') {
            $keyword = ClusterKeyword::find($id);
            if ($keyword) {
                KeywordCluster::where('id', $keyword->cluster_id)->increment('published_count');
            }
        }

        if ($status === 'failed') {
            $keyword = ClusterKeyword::find($id);
            if ($keyword) {
                KeywordCluster::where('id', $keyword->cluster_id)->increment('failed_count');
                ClusterKeyword::where('id', $id)->increment('retry_count');
            }
        }
    }

    public function getClusterProgress(int $clusterId): array
    {
        $cluster = KeywordCluster::findOrFail($clusterId);
        return $cluster->progress();
    }

    public function activateCluster(int $clusterId): void
    {
        KeywordCluster::where('id', $clusterId)->update(['status' => 'active']);
    }

    public function pauseCluster(int $clusterId): void
    {
        KeywordCluster::where('id', $clusterId)->update(['status' => 'paused']);
    }

    public function getAutomationSummary(): array
    {
        $userId = auth()->id();

        $clusters = KeywordCluster::where('user_id', $userId)->get();
        $summary = [];

        foreach ($clusters as $cluster) {
            $summary[] = [
                'id' => $cluster->id,
                'name' => $cluster->name,
                'status' => $cluster->status,
                'schedule' => $cluster->schedule,
                'progress' => $cluster->progress(),
            ];
        }

        return $summary;
    }

    public function logAutomation(
        int $clusterId,
        string $action,
        string $status,
        ?string $message = null,
        ?int $durationMs = null,
        ?int $keywordId = null,
    ): void {
        ClusterAutomationLog::create([
            'cluster_id' => $clusterId,
            'keyword_id' => $keywordId,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'duration_ms' => $durationMs,
            'created_at' => now(),
            'completed_at' => $status === 'completed' || $status === 'failed' ? now() : null,
        ]);
    }

    public function recordAnalytic(int $clusterId, array $data): void
    {
        ClusterAnalytic::updateOrCreate(
            ['cluster_id' => $clusterId, 'date' => today()],
            $data,
        );
    }

    public function recordKeywordAnalytic(int $clusterKeywordId, array $data): void
    {
        ClusterKeywordAnalytic::updateOrCreate(
            ['cluster_keyword_id' => $clusterKeywordId],
            $data,
        );
    }

    public function checkDailyQuota(int $clusterId): bool
    {
        $maxPerDay = (int) config('seo-cluster.automation.posts_per_day', 3);
        $todayPublished = ClusterKeyword::where('cluster_id', $clusterId)
            ->where('status', 'published')
            ->whereDate('published_at', today())
            ->count();

        return $todayPublished < $maxPerDay;
    }

    public function checkPostingHours(): bool
    {
        $start = config('seo-cluster.automation.post_time_start', '08:00');
        $end = config('seo-cluster.automation.post_time_end', '22:00');
        $now = now()->format('H:i');

        return $now >= $start && $now <= $end;
    }
}
