<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clipping_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opus_source_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('clips_received')->default(0);
            $table->unsignedInteger('clips_added')->default(0);
            $table->unsignedInteger('publications_added')->default(0);
            $table->timestamp('clips_completed_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['opus_source_id', 'id']);
        });
        Schema::create('clipping_sync_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clipping_sync_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opus_source_id')->constrained()->cascadeOnDelete();
            $table->char('observation_key', 64);
            $table->unique(['opus_source_id', 'observation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clipping_sync_observations');
        Schema::dropIfExists('clipping_sync_runs');
    }
};
