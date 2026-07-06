<?php

namespace Modules\MetaAdsImageGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdProject extends Model
{
    protected $fillable = [
        'user_id',
        'brand_kit_id',
        'name',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    public function brandKit(): BelongsTo
    {
        return $this->belongsTo(AdBrandKit::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AdAsset::class, 'project_id');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(AdGeneration::class, 'project_id');
    }
}