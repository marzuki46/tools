<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'business_name',
        'website_url',
        'description',
        'products_services',
        'target_audience',
        'usp',
        'writing_rules',
        'business_hours',
        'contact_email',
        'contact_phone',
        'address',
        'social_media',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'social_media' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function toPromptContext(): string
    {
        $parts = [];

        if ($this->business_name) {
            $parts[] = "Nama Bisnis: {$this->business_name}";
        }
        if ($this->website_url) {
            $parts[] = "Website: {$this->website_url}";
        }
        if ($this->description) {
            $parts[] = "Deskripsi Bisnis: {$this->description}";
        }
        if ($this->products_services) {
            $parts[] = "Produk/Jasa: {$this->products_services}";
        }
        if ($this->target_audience) {
            $parts[] = "Target Audiens: {$this->target_audience}";
        }
        if ($this->usp) {
            $parts[] = "Keunggulan: {$this->usp}";
        }
        if ($this->writing_rules) {
            $parts[] = "ATURAN MENULIS (ikuti dengan ketat): {$this->writing_rules}";
        }
        if ($this->contact_email) {
            $parts[] = "Email: {$this->contact_email}";
        }
        if ($this->contact_phone) {
            $parts[] = "Telepon: {$this->contact_phone}";
        }
        if ($this->address) {
            $parts[] = "Alamat: {$this->address}";
        }
        if ($this->business_hours) {
            $parts[] = "Jam Operasional: {$this->business_hours}";
        }
        if ($this->social_media) {
            $sm = collect($this->social_media)->filter()->map(fn($v, $k) => ucfirst($k) . ": {$v}")->implode(', ');
            if ($sm) {
                $parts[] = "Sosial Media: {$sm}";
            }
        }

        if (empty($parts)) {
            return '';
        }

        return "\n\n---\nINFORMASI BISNIS (promosikan secara natural dalam konten jika relevan):\n" . implode("\n", $parts) . "\n---\n";
    }
}
