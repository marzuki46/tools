<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_key_website', function (Blueprint $table) {
            $table->string('wp_url', 255)->nullable()->after('site_name');
            $table->string('wp_username', 100)->nullable()->after('wp_url');
            $table->text('wp_app_password')->nullable()->after('wp_username');
        });
    }

    public function down(): void
    {
        Schema::table('api_key_website', function (Blueprint $table) {
            $table->dropColumn(['wp_url', 'wp_username', 'wp_app_password']);
        });
    }
};
