<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_provider_usage', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('external_id');
            $table->unique(['provider', 'external_id']);
            $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operation_id')->nullable()->constrained('generation_operations')->nullOnDelete();
            $table->string('requested_model')->nullable();
            $table->string('result_model')->nullable();
            $table->string('input_mode')->nullable();
            $table->string('resolution')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedBigInteger('completion_tokens')->nullable();
            $table->string('status');
            $table->dateTime('observed_at');
            $table->index(['observed_at', 'result_model']);
            $table->unsignedBigInteger('charge_e8')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('finance_reference')->nullable();
            $table->dateTime('reconciled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_provider_usage');
    }
};
