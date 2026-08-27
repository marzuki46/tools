<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_source_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geo_project_id')->constrained('geo_projects')->cascadeOnDelete();
            $table->string('source_url', 500);
            $table->string('source_host', 255)->nullable();
            $table->mediumText('raw_text')->nullable();
            $table->mediumText('sanitized_facts')->nullable(); // fakta tanpa brand, hasil sintesis
            $table->string('content_hash', 64)->nullable();
            $table->boolean('is_synthesis')->default(false);
            $table->string('fetch_status', 20)->default('pending'); // pending, success, failed
            $table->string('fetch_error', 500)->nullable();
            $table->timestamps();
            $table->index('geo_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_source_facts');
    }
};
