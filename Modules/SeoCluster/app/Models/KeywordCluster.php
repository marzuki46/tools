<?php

namespace Modules\SeoCluster\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeywordCluster extends Model
{
    protected $table = 'keyword_clusters';

    protected $fillable = [
        'user_id',
        'name',
        'parent_keyword',
        'description',
        'status',
        'schedule',
        'total_keywords',
        'published_count',
        'failed_count',
        'image_keyword',
        'image_source',
        'image_enabled',
        'image_per_article',
        'webp_quality',
    ];

    protected $casts = [
        'image_enabled' => 'boolean',
        'total_keywords' => 'integer',
        'published_count' => 'integer',
        'failed_count' => 'integer',
        'image_per_article' => 'integer',
        'webp_quality' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(ClusterKeyword::class, 'cluster_id');
    }

    public function automationLogs(): HasMany
    {
        return $this->hasMany(ClusterAutomationLog::class, 'cluster_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(ClusterAnalytic::class, 'cluster_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeForUser($q, $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function progress(): array
    {
        $total = $this->keywords()->count();
        $published = $this->keywords()->where('status', 'published')->count();
        $failed = $this->keywords()->where('status', 'failed')->count();
        $pending = $total - $published - $failed;

        return [
            'total' => $total,
            'published' => $published,
            'failed' => $failed,
            'pending' => max(0, $pending),
            'percent' => $total > 0 ? round(($published / $total) * 100) : 0,
        ];
    }
}
