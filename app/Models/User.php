<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class)
            ->withPivot('is_active', 'config')
            ->withTimestamps();
    }

    public function activeTools(): BelongsToMany
    {
        return $this->tools()->wherePivot('is_active', true)->where('tools.is_active', true);
    }
    
    public function hasToolAccess(string $slug): bool
    {
        return $this->activeTools()->where('tools.slug', $slug)->exists();
    }
}
