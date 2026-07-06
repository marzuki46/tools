<?php

namespace Modules\MetaAdsImageGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdBrandKit extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'logo_path',
        'primary_color',
        'secondary_color',
        'font_family',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        // Assuming App\Models\User exists in the main app
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }
}