<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKeyWebsite extends Model
{
    protected $table = 'api_key_website';

    protected $fillable = [
        'api_key_id',
        'domain',
        'is_active',
        'last_used_at',
        'last_ip',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
