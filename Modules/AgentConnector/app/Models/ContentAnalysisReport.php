<?php

namespace Modules\AgentConnector\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentAnalysisReport extends Model
{
    use HasFactory;

    protected $table = 'content_analysis_reports';

    protected $fillable = [
        'user_id',
        'wp_post_id',
        'title',
        'url',
        'keyword',
        'total_score',
        'seo_score',
        'structure_score',
        'readability_score',
        'image_score',
        'details',
        'issues',
        'status',
        'scheduled_at',
        'optimized_at',
    ];

    protected $casts = [
        'details' => 'array',
        'issues' => 'array',
        'scheduled_at' => 'datetime',
        'optimized_at' => 'datetime',
    ];

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
