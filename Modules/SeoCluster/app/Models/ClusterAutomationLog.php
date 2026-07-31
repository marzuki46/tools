<?php

namespace Modules\SeoCluster\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClusterAutomationLog extends Model
{
    protected $table = 'cluster_automation_logs';

    public $timestamps = false;

    protected $fillable = [
        'cluster_id',
        'keyword_id',
        'action',
        'status',
        'message',
        'duration_ms',
        'created_at',
        'completed_at',
    ];

    protected $casts = [
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(KeywordCluster::class, 'cluster_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(ClusterKeyword::class, 'keyword_id');
    }
}
