<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('keyword_clusters')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('keywords_processed')->default(0);
            $table->unsignedInteger('keywords_published')->default(0);
            $table->unsignedInteger('keywords_failed')->default(0);
            $table->float('avg_duration_minutes')->default(0);
            $table->float('avg_quality_score')->default(0);
            $table->float('success_rate')->default(0);
            $table->timestamps();

            $table->unique(['cluster_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_analytics');
    }
};
