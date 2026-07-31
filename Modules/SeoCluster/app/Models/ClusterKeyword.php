<?php

namespace Modules\SeoCluster\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClusterKeyword extends Model
{
    protected $table = 'cluster_keywords';

    protected $fillable = [
        'cluster_id',
        'keyword',
        'status',
        'keyword_research_id',
        'content_generation_id',
        'post_url',
        'published_at',
        'error_message',
        'priority',
        'retry_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'priority' => 'integer',
        'retry_count' => 'integer',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(KeywordCluster::class, 'cluster_id');
    }

    public function analytic()
    {
        return $this->hasOne(ClusterKeywordAnalytic::class, 'cluster_keyword_id');
    }
}
