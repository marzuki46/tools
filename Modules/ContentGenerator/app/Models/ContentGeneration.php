<?php

namespace Modules\ContentGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentGeneration extends Model
{
    protected $table = 'content_generations';

    protected $fillable = [
        'user_id',
        'target_keyword',
        'locale',
        'tone',
        'keyword_research_id',
        'business_profile_id',
        'lsi_keywords',
        'entities',
        'phase_1_content',
        'phase_2_questions',
        'phase_3_content',
        'meta_title',
        'meta_description',
        'status',
        'current_phase',
        'raw_response',
    ];

    protected $casts = [
        'lsi_keywords' => 'array',
        'entities' => 'array',
        'phase_2_questions' => 'array',
        'raw_response' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }
}
