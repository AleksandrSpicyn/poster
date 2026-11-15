<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('generation_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prompt_id')->constrained('prompts')->restrictOnDelete();
            $table->string('stage');
            $table->string('status')->default('planned');
            $table->string('request_key', 100);
            $table->string('idempotency_key', 64)->unique();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->json('request_snapshot');
            $table->json('result_snapshot')->nullable();
            $table->string('external_id')->nullable();
            $table->string('error_code')->nullable();
            $table->unsignedInteger('check_count')->default(0);
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamps();
            $table->unique(['prompt_id', 'stage']);
            $table->unique(['request_key', 'stage']);
            $table->index(['status', 'next_check_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generation_operations');
    }
};
