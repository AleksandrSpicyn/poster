<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('video_upscale_operations', function (Blueprint $table): void {
            $table->foreignId('episode_assembly_id')->nullable()->change();
            $table->foreignId('series_assembly_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('video_upscale_operations')->restrictOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('video_upscale_operations')->whereNull('episode_assembly_id')->orWhereNotNull('parent_id')->exists()) {
            throw new RuntimeException('The previous schema cannot retain standalone, series or multipart exports.');
        }

        Schema::table('video_upscale_operations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('prompt_id');
            $table->dropConstrainedForeignId('series_assembly_id');
            $table->foreignId('episode_assembly_id')->nullable(false)->change();
        });
    }
};
