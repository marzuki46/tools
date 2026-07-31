<?php

namespace Modules\SeoCluster\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClusterAnalytic extends Model
{
    protected $table = 'cluster_analytics';

    protected $fillable = [
        'cluster_id',
        'date',
        'keywords_processed',
        'keywords_published',
        'keywords_failed',
        'avg_duration_minutes',
        'avg_quality_score',
        'success_rate',
    ];

    protected $casts = [
        'date' => 'date',
        'keywords_processed' => 'integer',
        'keywords_published' => 'integer',
        'keywords_failed' => 'integer',
        'avg_duration_minutes' => 'float',
        'avg_quality_score' => 'float',
        'success_rate' => 'float',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(KeywordCluster::class, 'cluster_id');
    }
}
