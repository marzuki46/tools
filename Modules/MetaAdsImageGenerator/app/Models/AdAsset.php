<?php

namespace Modules\MetaAdsImageGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdAsset extends Model
{
    protected $fillable = [
        'project_id',
        'file_path',
        'original_name',
        'mime_type',
        'size_kb',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(AdProject::class, 'project_id');
    }
}