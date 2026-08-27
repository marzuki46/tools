<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_content_diffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geo_project_id')->constrained('geo_projects')->cascadeOnDelete();
            $table->string('before_hash', 64)->nullable();
            $table->string('after_hash', 64)->nullable();
            $table->mediumText('inline_diff_html')->nullable();
            $table->mediumText('side_by_side_html')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_content_diffs');
    }
};
