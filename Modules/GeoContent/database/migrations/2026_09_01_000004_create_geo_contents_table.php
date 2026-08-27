<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geo_project_id')->constrained('geo_projects')->cascadeOnDelete();
            $table->mediumText('before_snapshot')->nullable(); // untuk mode revisi
            $table->mediumText('final_content')->nullable();
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->string('status', 30)->default('draft'); // draft, completed, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_contents');
    }
};
