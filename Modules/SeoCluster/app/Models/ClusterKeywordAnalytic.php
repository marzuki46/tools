<?php

namespace Modules\SeoCluster\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClusterKeywordAnalytic extends Model
{
    protected $table = 'cluster_keyword_analytics';

    protected $fillable = [
        'cluster_keyword_id',
        'post_url',
        'published_at',
        'posted_hour',
        'quality_score',
        'word_count',
        'tokens_used',
        'image_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'posted_hour' => 'integer',
        'quality_score' => 'float',
        'word_count' => 'integer',
        'tokens_used' => 'integer',
        'image_count' => 'integer',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(ClusterKeyword::class, 'cluster_keyword_id');
    }
}
