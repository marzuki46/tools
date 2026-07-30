<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->text('key_encrypted')->nullable()->after('key');
            $table->string('key_prefix', 10)->nullable()->after('key_encrypted');
            $table->integer('max_sites')->nullable()->after('key_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['key_encrypted', 'key_prefix', 'max_sites']);
        });
    }
};
