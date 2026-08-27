<?php

namespace Modules\GeoContent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoProject extends Model
{
    protected $table = 'geo_projects';

    protected $fillable = [
        'user_id',
        'api_key_website_id',
        'keyword_utama',
        'locale',
        'competitor_urls',
        'competitor_brands',
        'keyword_research_id',
        'status',
        'mode',
        'wp_post_id',
        'wp_post_before_snapshot',
        'error_message',
    ];

    protected $casts = [
        'competitor_urls' => 'array',
        'competitor_brands' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ApiKeyWebsite::class, 'api_key_website_id');
    }

    public function keywordResearch(): BelongsTo
    {
        return $this->belongsTo(\Modules\KeywordResearch\Models\KeywordResearch::class, 'keyword_research_id');
    }

    public function sourceFacts(): HasMany
    {
        return $this->hasMany(GeoSourceFact::class, 'geo_project_id');
    }

    public function criticalFindings(): HasMany
    {
        return $this->hasMany(GeoCriticalFinding::class, 'geo_project_id')->orderBy('rank');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(GeoContent::class, 'geo_project_id');
    }

    public function latestContent(): HasOne
    {
        return $this->hasOne(GeoContent::class, 'geo_project_id')->latestOfMany();
    }

    public function diff(): HasOne
    {
        return $this->hasOne(GeoContentDiff::class, 'geo_project_id')->latestOfMany();
    }
}
