<?php

namespace Modules\MetaAdsImageGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdGeneration extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'preset_id',
        'asset_id',
        'model_asset_id',
        'input_form',
        'compiled_prompt',
        'ai_provider',
        'ai_model',
        'ai_raw_response',
        'seed',
        'base_image_path',
        'status',
        'credit_used',
        'moderation_flag',
    ];

    protected $casts = [
        'input_form' => 'array',
        'ai_raw_response' => 'array',
        'moderation_flag' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(AdProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(AdPreset::class, 'preset_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AdAsset::class, 'asset_id');
    }

    public function modelAsset(): BelongsTo
    {
        return $this->belongsTo(AdAsset::class, 'model_asset_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(AdExport::class, 'generation_id');
    }
}