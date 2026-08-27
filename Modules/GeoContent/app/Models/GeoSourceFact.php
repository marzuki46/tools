<?php

namespace Modules\GeoContent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoSourceFact extends Model
{
    protected $table = 'geo_source_facts';

    protected $fillable = [
        'geo_project_id',
        'source_url',
        'source_host',
        'raw_text',
        'sanitized_facts',
        'content_hash',
        'is_synthesis',
        'fetch_status',
        'fetch_error',
    ];

    protected $casts = [
        'is_synthesis' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(GeoProject::class, 'geo_project_id');
    }
}
