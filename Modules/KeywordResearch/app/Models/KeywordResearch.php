<?php

namespace Modules\KeywordResearch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordResearch extends Model
{
    protected $table = 'keyword_researches';

    protected $fillable = [
        'user_id',
        'api_key_website_id',
        'target_keyword',
        'locale',
        'lsi_count',
        'entities_count',
        'lsi_keywords',
        'entities',
        'raw_response',
        'status',
        'source',
        'webhook_url',
        'webhook_secret',
        'webhook_sent_at',
        'tokens_in',
        'tokens_out',
        'tokens_total',
    ];

    protected $casts = [
        'lsi_keywords' => 'array',
        'entities' => 'array',
        'raw_response' => 'array',
        'webhook_sent_at' => 'datetime',
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

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeDone($q)
    {
        return $q->where('status', 'completed');
    }

    public function scopeFailed($q)
    {
        return $q->where('status', 'failed');
    }
}
