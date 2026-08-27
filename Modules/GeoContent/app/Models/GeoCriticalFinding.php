<?php

namespace Modules\GeoContent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoCriticalFinding extends Model
{
    protected $table = 'geo_critical_findings';

    protected $fillable = [
        'geo_project_id',
        'question',
        'rank',
        'is_edited',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(GeoProject::class, 'geo_project_id');
    }
}
