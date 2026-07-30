<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'seo-agent.fonnte.token',
                'value' => '',
                'group' => 'seo-agent',
                'type' => 'password',
                'description' => 'Fonnte API Token — ambil dari dashboard Fonnte',
            ],
            [
                'key' => 'seo-agent.fonnte.api_url',
                'value' => 'https://api.fonnte.com',
                'group' => 'seo-agent',
                'type' => 'url',
                'description' => 'Fonnte API URL',
            ],
            [
                'key' => 'seo-agent.fonnte.webhook_secret',
                'value' => '',
                'group' => 'seo-agent',
                'type' => 'password',
                'description' => 'Fonnte Webhook Secret (optional)',
            ],
            [
                'key' => 'seo-agent.allowed_numbers',
                'value' => '',
                'group' => 'seo-agent',
                'type' => 'text',
                'description' => 'Nomor WA yang diizinkan, pisahkan dengan koma (kosongkan = semua nomor)',
            ],
            [
                'key' => 'seo-agent.default_user_id',
                'value' => '1',
                'group' => 'seo-agent',
                'type' => 'text',
                'description' => 'User ID default untuk riset & konten (biasanya admin)',
            ],
            [
                'key' => 'seo-agent.max_message_length',
                'value' => '1500',
                'group' => 'seo-agent',
                'type' => 'text',
                'description' => 'Maksimal panjang pesan WA (karakter)',
            ],
            [
                'key' => 'seo-agent.rate_limit.max_attempts',
                'value' => '10',
                'group' => 'seo-agent',
                'type' => 'text',
                'description' => 'Maksimal request per menit per nomor',
            ],
        ];

        foreach ($settings as $s) {
            Setting::firstOrCreate(
                ['key' => $s['key']],
                $s
            );
        }
    }

    public function down(): void
    {
        Setting::where('group', 'seo-agent')->delete();
    }
};
