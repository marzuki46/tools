<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAnalysis extends Model
{
    protected $fillable = [
        'user_id',
        'url',
        'keyword',
        'title',
        'meta_description',
        'score',
        'result',
    ];

    protected $casts = [
        'result' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function grade(): string
    {
        return match (true) {
            $this->score >= 90 => 'A',
            $this->score >= 75 => 'B',
            $this->score >= 55 => 'C',
            $this->score >= 35 => 'D',
            default => 'E',
        };
    }

    public function gradeColor(): string
    {
        return match ($this->grade()) {
            'A' => 'text-green-600',
            'B' => 'text-emerald-500',
            'C' => 'text-yellow-500',
            'D' => 'text-orange-500',
            'E' => 'text-red-600',
        };
    }

    public function gradeBg(): string
    {
        return match ($this->grade()) {
            'A' => 'bg-green-100',
            'B' => 'bg-emerald-100',
            'C' => 'bg-yellow-100',
            'D' => 'bg-orange-100',
            'E' => 'bg-red-100',
        };
    }
}
