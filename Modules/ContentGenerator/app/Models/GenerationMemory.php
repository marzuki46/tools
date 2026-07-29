<?php

namespace Modules\ContentGenerator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationMemory extends Model
{
    protected $table = 'generation_memories';

    protected $fillable = [
        'user_id',
        'content_generation_id',
        'keyword',
        'locale',
        'tone',
        'lsi_keywords',
        'entities',
        'summary',
        'embedding',
        'quality_score',
        'is_reference',
        'feedback',
    ];

    protected $casts = [
        'lsi_keywords' => 'array',
        'entities' => 'array',
        'quality_score' => 'float',
        'is_reference' => 'boolean',
    ];

    public function scopeReference($q)
    {
        return $q->where('is_reference', true);
    }

    public function scopeByUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(ContentGeneration::class, 'content_generation_id');
    }
}
