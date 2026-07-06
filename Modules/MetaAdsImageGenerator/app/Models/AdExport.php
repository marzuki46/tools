<?php

namespace Modules\MetaAdsImageGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdExport extends Model
{
    protected $fillable = [
        'generation_id',
        'placement',
        'width',
        'height',
        'final_image_path',
        'overlay_config',
        'downloaded_at',
    ];

    protected $casts = [
        'overlay_config' => 'array',
        'downloaded_at' => 'datetime',
    ];

    public function generation(): BelongsTo
    {
        return $this->belongsTo(AdGeneration::class, 'generation_id');
    }
}