<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_critical_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geo_project_id')->constrained('geo_projects')->cascadeOnDelete();
            $table->text('question');
            $table->unsignedTinyInteger('rank')->default(1);
            $table->boolean('is_edited')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_critical_findings');
    }
};
