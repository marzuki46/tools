<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('keyword')->nullable();
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->integer('score')->default(0)->comment('Overall SEO score 0-100');
            $table->json('result')->comment('Full analysis result');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_analyses');
    }
};
