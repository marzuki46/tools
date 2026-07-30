<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'seo-agent.telegram.token',
                'value' => '',
                'group' => 'seo-agent',
                'type' => 'password',
                'description' => 'Telegram Bot Token — dapatkan dari @BotFather',
            ],
            [
                'key' => 'seo-agent.allowed_numbers',
                'value' => '',
                'group' => 'seo-agent',
                'type' => 'text',
                'description' => 'Chat ID Telegram yang diizinkan, pisahkan dengan koma (kosongkan = semua)',
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
                'value' => '4000',
                'group' => 'seo-agent',
                'type' => 'text',
                'description' => 'Maksimal panjang pesan Telegram (karakter)',
            ],
            [
                'key' => 'seo-agent.rate_limit.max_attempts',
                'value' => '10',
                'group' => 'seo-agent',
                'type' => 'text',
                'description' => 'Maksimal request per menit per user',
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
