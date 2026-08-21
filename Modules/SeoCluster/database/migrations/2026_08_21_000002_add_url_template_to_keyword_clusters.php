<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->string('url_template')->nullable()->after('pillar_generation_id')
                ->comment('Template URL situs WP, misal https://situs.com/{slug}/ — dari permalink structure plugin');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->dropColumn('url_template');
        });
    }
};
