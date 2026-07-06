<?php

namespace Modules\MetaAdsImageGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'generation_id',
        'provider',
        'tokens_or_units',
        'estimated_cost',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(AdGeneration::class, 'generation_id');
    }
}