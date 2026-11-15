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
        Schema::table('generation_operations', function (Blueprint $table): void {
            $table->foreignId('production_run_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::table('publication_requests', function (Blueprint $table): void {
            $table->foreignId('production_run_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publication_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_run_id');
        });

        Schema::table('generation_operations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_run_id');
        });
    }
};
