<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_generations', function (Blueprint $table) {
            $table->foreignId('model_asset_id')->nullable()->after('asset_id')->constrained('ad_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ad_generations', function (Blueprint $table) {
            $table->dropForeign(['model_asset_id']);
            $table->dropColumn('model_asset_id');
        });
    }
};
