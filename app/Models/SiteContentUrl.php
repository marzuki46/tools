<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteContentUrl extends Model
{
    protected $fillable = [
        'user_id',
        'api_key_website_id',
        'url',
        'title',
        'keyword',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(ApiKeyWebsite::class, 'api_key_website_id');
    }

    public function scopeForWebsite($query, ?int $websiteId)
    {
        return $websiteId
            ? $query->where('api_key_website_id', $websiteId)
            : $query->whereNull('api_key_website_id');
    }
}
