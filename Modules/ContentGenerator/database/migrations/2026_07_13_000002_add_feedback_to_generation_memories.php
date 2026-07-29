<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_memories', function (Blueprint $table) {
            $table->boolean('is_reference')->default(false)->after('quality_score');
            $table->text('feedback')->nullable()->after('is_reference');
        });
    }

    public function down(): void
    {
        Schema::table('generation_memories', function (Blueprint $table) {
            $table->dropColumn(['is_reference', 'feedback']);
        });
    }
};
