<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->string('wp_entity_type', 30)->nullable()->after('wp_url');
            $table->unsignedBigInteger('wp_entity_id')->nullable()->after('wp_entity_type');
            $table->string('content_key', 255)->nullable()->after('wp_entity_id');
            $table->unsignedBigInteger('wp_post_id')->nullable()->after('content_key');

            $table->index(['user_id', 'api_key_website_id', 'content_key'], 'cg_identity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropIndex('cg_identity_idx');
            $table->dropColumn(['wp_entity_type', 'wp_entity_id', 'content_key', 'wp_post_id']);
        });
    }
};
