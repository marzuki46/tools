<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('keyword_clusters')->cascadeOnDelete();
            $table->unsignedBigInteger('keyword_id')->nullable();
            $table->string('action', 30);
            $table->string('status', 20);
            $table->text('message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->index('cluster_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_automation_logs');
    }
};
