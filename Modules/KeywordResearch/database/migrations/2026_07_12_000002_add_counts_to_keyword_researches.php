<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_researches', function (Blueprint $table) {
            $table->integer('lsi_count')->nullable()->after('locale');
            $table->integer('entities_count')->nullable()->after('lsi_count');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_researches', function (Blueprint $table) {
            $table->dropColumn(['lsi_count', 'entities_count']);
        });
    }
};
