<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_keyword');
            $table->string('locale', 10)->default('id');
            $table->string('tone', 50)->default('informative');
            $table->unsignedBigInteger('keyword_research_id')->nullable();
            $table->json('lsi_keywords')->nullable();
            $table->json('entities')->nullable();
            $table->longText('phase_1_content')->nullable();
            $table->json('phase_2_questions')->nullable();
            $table->longText('phase_3_content')->nullable();
            $table->string('status', 30)->default('draft');
            $table->unsignedTinyInteger('current_phase')->default(0);
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_generations');
    }
};
