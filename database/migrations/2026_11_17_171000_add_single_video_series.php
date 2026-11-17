<?php

declare(strict_types=1);

use App\Enums\SeriesOutputMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table): void {
            $table->string('output_mode')->default(SeriesOutputMode::EPISODES->value);
            $table->json('cycle_plan')->nullable();
            $table->timestamp('cycle_paused_at')->nullable();
            $table->timestamp('release_started_at')->nullable();
        });
        Schema::create('series_assemblies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('status')->default('draft');
            $table->json('manifest');
            $table->string('manifest_hash', 64);
            $table->unsignedInteger('lock_version')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->json('review')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['series_id', 'number']);
        });
        Schema::create('series_assembly_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('series_assembly_id')->constrained()->restrictOnDelete();
            $table->foreignId('episode_assembly_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->unique(['series_assembly_id', 'position']);
            $table->unique(['series_assembly_id', 'episode_assembly_id'], 'series_assembly_items_source_unique');
        });
        Schema::table('series', function (Blueprint $table): void {
            $table->foreignId('final_assembly_id')->nullable()->constrained('series_assemblies')->restrictOnDelete();
        });
        Schema::table('production_runs', function (Blueprint $table): void {
            $table->foreignId('series_assembly_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('input_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('stage_started_at')->nullable();
        });
        Schema::table('publication_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('episode_assembly_id')->nullable()->change();
            $table->foreignId('series_assembly_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('release_key')->nullable();
            $table->unique(['series_assembly_id', 'channel_id']);
            $table->unique(['release_key', 'channel_id']);
        });
        Schema::table('posts', function (Blueprint $table): void {
            $table->foreignId('series_assembly_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn(Blueprint $table) => $table->dropConstrainedForeignId('series_assembly_id'));
        Schema::table('publication_requests', function (Blueprint $table): void {
            $table->dropUnique(['series_assembly_id', 'channel_id']);
            $table->dropUnique(['release_key', 'channel_id']);
            $table->dropConstrainedForeignId('series_assembly_id');
            $table->dropColumn('release_key');
        });
        Schema::table('production_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('series_assembly_id');
            $table->dropColumn(['input_snapshot', 'lock_version', 'stage_started_at']);
        });
        Schema::table('series', fn(Blueprint $table) => $table->dropConstrainedForeignId('final_assembly_id'));
        Schema::dropIfExists('series_assembly_items');
        Schema::dropIfExists('series_assemblies');
        Schema::table('series', fn(Blueprint $table) => $table->dropColumn(['output_mode', 'cycle_plan', 'cycle_paused_at', 'release_started_at']));
    }
};
