<?php

namespace Modules\GeoContent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoContentDiff extends Model
{
    protected $table = 'geo_content_diffs';

    protected $fillable = [
        'geo_project_id',
        'before_hash',
        'after_hash',
        'inline_diff_html',
        'side_by_side_html',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(GeoProject::class, 'geo_project_id');
    }
}
