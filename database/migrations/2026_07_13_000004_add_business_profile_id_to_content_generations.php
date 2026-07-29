<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->foreignId('business_profile_id')->nullable()->constrained('business_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropForeign(['business_profile_id']);
            $table->dropColumn('business_profile_id');
        });
    }
};
