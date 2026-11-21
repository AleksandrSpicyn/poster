<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('clipping_workspace')->nullable()->unique();
        });
        Schema::table('channels', function (Blueprint $table): void {
            $table->string('external_account')->nullable();
            $table->unique(['type', 'external_account']);
        });
        Schema::create('opus_game_trends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('game');
            $table->json('entries');
            $table->timestamps();
            $table->unique(['project_id', 'game']);
        });
        Schema::create('opus_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('external_id');
            $table->text('title')->nullable();
            $table->string('game')->nullable();
            $table->unique(['project_id', 'external_id']);
            $table->timestamps();
        });
        Schema::create('opus_clips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opus_source_id')->constrained()->cascadeOnDelete();
            $table->string('clip_id');
            $table->text('title')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->float('score')->nullable();
            $table->json('publications');
            $table->timestamps();
            $table->unique(['opus_source_id', 'clip_id']);
        });
        Schema::create('opus_clip_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opus_clip_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('media_id', 100);
            $table->date('observed_on');
            $table->json('metrics');
            $table->timestamps();
            $table->unique(['opus_clip_id', 'platform', 'media_id', 'observed_on'], 'opus_metrics_observation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opus_clip_metrics');
        Schema::dropIfExists('opus_clips');
        Schema::dropIfExists('opus_sources');
        Schema::dropIfExists('opus_game_trends');
        Schema::table('channels', function (Blueprint $table): void {
            $table->dropUnique(['type', 'external_account']);
            $table->dropColumn('external_account');
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['clipping_workspace']);
            $table->dropColumn('clipping_workspace');
        });
    }
};
