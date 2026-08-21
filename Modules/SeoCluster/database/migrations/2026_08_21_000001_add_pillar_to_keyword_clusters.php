<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->string('pillar_post_url')->nullable()->after('webp_quality');
            $table->unsignedBigInteger('pillar_generation_id')->nullable()->after('pillar_post_url');
        });

        Schema::table('cluster_keywords', function (Blueprint $table) {
            $table->unsignedBigInteger('wp_post_id')->nullable()->after('post_url');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->dropColumn(['pillar_post_url', 'pillar_generation_id']);
        });

        Schema::table('cluster_keywords', function (Blueprint $table) {
            $table->dropColumn('wp_post_id');
        });
    }
};
