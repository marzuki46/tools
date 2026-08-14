<?php

namespace Modules\ContentGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentGeneration extends Model
{
    protected $table = 'content_generations';

    protected $fillable = [
        'user_id',
        'api_key_website_id',
        'target_keyword',
        'locale',
        'tone',
        'keyword_research_id',
        'business_profile_id',
        'content_brief_id',
        'lsi_keywords',
        'entities',
        'link_sources',
        'include_external_links',
        'target_words',
        'phase_1_content',
        'phase_2_questions',
        'phase_3_content',
        'meta_title',
        'meta_description',
        'status',
        'current_phase',
        'raw_response',
        'tokens_in',
        'tokens_out',
        'tokens_total',
    ];

    protected $casts = [
        'lsi_keywords' => 'array',
        'entities' => 'array',
        'link_sources' => 'array',
        'target_words' => 'integer',
        'include_external_links' => 'boolean',
        'phase_2_questions' => 'array',
        'raw_response' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'tokens_total' => 'integer',
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
