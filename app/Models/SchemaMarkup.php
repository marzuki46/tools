<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SchemaMarkup extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'schema_type',
        'target_url',
        'sourceable_type',
        'sourceable_id',
        'data',
        'generated',
        'use_ai',
    ];

    protected $casts = [
        'data' => 'array',
        'generated' => 'array',
        'use_ai' => 'boolean',
    ];

    public const TYPES = [
        'Article' => 'Artikel',
        'FAQPage' => 'FAQ',
        'Product' => 'Produk',
        'LocalBusiness' => 'Bisnis Lokal',
        'BreadcrumbList' => 'Breadcrumb',
        'Review' => 'Review',
        'Recipe' => 'Resep',
        'VideoObject' => 'Video',
        'HowTo' => 'Panduan',
        'Event' => 'Event',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->schema_type] ?? $this->schema_type;
    }

    public function toScriptTag(): string
    {
        return '<script type="application/ld+json">' . PHP_EOL
            . json_encode($this->generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . PHP_EOL . '</script>';
    }
}
