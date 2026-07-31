<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_analysis_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wp_post_id')->nullable();
            $table->string('title')->nullable();
            $table->string('url')->nullable();
            $table->string('keyword')->nullable();
            $table->unsignedSmallInteger('total_score')->default(0);
            $table->unsignedSmallInteger('seo_score')->default(0);
            $table->unsignedSmallInteger('structure_score')->default(0);
            $table->unsignedSmallInteger('readability_score')->default(0);
            $table->unsignedSmallInteger('image_score')->default(0);
            $table->json('details')->nullable();
            $table->json('issues')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('optimized_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_analysis_reports');
    }
};
