<?php

namespace Modules\GeoContent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoContent extends Model
{
    protected $table = 'geo_contents';

    protected $fillable = [
        'geo_project_id',
        'before_snapshot',
        'final_content',
        'meta_title',
        'meta_description',
        'word_count',
        'tokens_in',
        'tokens_out',
        'status',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(GeoProject::class, 'geo_project_id');
    }
}
