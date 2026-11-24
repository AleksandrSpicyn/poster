<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_upscale_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('episode_assembly_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiator_id')->constrained('users')->restrictOnDelete();
            $table->char('operation_key', 64);
            $table->unsignedInteger('attempt');
            $table->string('provider');
            $table->string('status');
            $table->json('source_snapshot');
            $table->json('profile_snapshot');
            $table->json('receipt')->nullable();
            $table->foreignId('source_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->foreignId('result_media_id')->nullable()->unique()->constrained('media')->restrictOnDelete();
            $table->char('source_sha256', 64)->nullable();
            $table->char('result_sha256', 64)->nullable();
            $table->unsignedBigInteger('estimated_cost_e8');
            $table->unsignedBigInteger('actual_cost_e8')->nullable();
            $table->char('currency', 3);
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_class')->nullable();
            $table->timestamps();
            $table->unique(['operation_key', 'attempt']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_upscale_operations');
    }
};
