<?php

namespace App\Models\Websites;

use App\Models\User;
use App\Models\Tools\Tool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Website extends Model
{
    protected $fillable = [
        'user_id',
        'domain',
        'name',
        'description',
        'is_verified',
        'is_active',
        'verified_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'website_tool')
            ->withPivot('is_active', 'config', 'api_key_hash', 'last_used_at', 'last_ip')
            ->withTimestamps();
    }

    public function activeTools(): BelongsToMany
    {
        return $this->tools()
            ->wherePivot('is_active', true)
            ->where('tools.is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function hasToolAccess(string $slug): bool
    {
        return $this->activeTools()->where('tools.slug', $slug)->exists();
    }
}
