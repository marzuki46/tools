<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'ai.system_prompt'],
            [
                'value' => 'Anda adalah asisten penulis konten profesional. Selalu gunakan format Markdown untuk struktur artikel.',
                'group' => 'ai',
                'type' => 'text',
                'description' => 'System prompt untuk AI content generator',
            ]
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'ai.system_prompt')->delete();
    }
};
