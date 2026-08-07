<?php

namespace Modules\ContentGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentBrief extends Model
{
    protected $table = 'content_briefs';

    protected $fillable = [
        'user_id',
        'api_key_website_id',
        'target_keyword',
        'locale',
        'meta_title',
        'h1_tag',
        'url_slug',
        'target_audience',
        'pain_point',
        'local_entities',
        'keywords',
        'raw_response',
        'status',
        'error_message',
    ];

    protected $casts = [
        'local_entities' => 'array',
        'keywords' => 'array',
        'raw_response' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    public function apiKeyWebsite(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ApiKeyWebsite::class, 'api_key_website_id');
    }
}
