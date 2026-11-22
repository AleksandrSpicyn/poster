<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', static function (Blueprint $table): void {
            $table->index(['owner_id', 'created_at', 'id']);
            $table->index(['owner_id', 'updated_at', 'id']);
        });
        Schema::table('channels', static function (Blueprint $table): void {
            $table->index(['owner_id', 'name', 'id']);
        });

        // MySQL requires a prefix length for the historical TEXT provider ID.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX prompts_audio_task ON prompts (audio_id(128))');
        } else {
            Schema::table('prompts', static fn(Blueprint $table) => $table->index('audio_id', 'prompts_audio_task'));
        }

        Schema::table('episode_assemblies', static function (Blueprint $table): void {
            $table->index(['status', 'updated_at']);
            $table->index(['status', 'started_at']);
        });
        Schema::table('publication_requests', static function (Blueprint $table): void {
            $table->index(['status', 'submitted_at']);
            $table->index(['status', 'scheduled_at']);
        });
        Schema::table('prompts', static function (Blueprint $table): void {
            $table->index(['shot_id', 'created_at', 'id']);
        });
        Schema::table('opus_clip_metrics', static function (Blueprint $table): void {
            $table->index(['observed_on', 'updated_at', 'id']);
        });
        Schema::table('publication_metric_origins', static function (Blueprint $table): void {
            $table->index(['owner_id', 'project_id', 'publication_key'], 'metric_origins_project_key');
        });
    }

    public function down(): void
    {
        // InnoDB may replace its implicit foreign-key index with a new composite index.
        foreach (['projects', 'channels'] as $name) {
            if (DB::connection()->getDriverName() === 'mysql' && ! Schema::hasIndex($name, ['owner_id'])) {
                Schema::table($name, static fn(Blueprint $table) => $table->index('owner_id', $name . '_owner_id_foreign'));
            }
        }

        Schema::table('projects', static function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'created_at', 'id']);
            $table->dropIndex(['owner_id', 'updated_at', 'id']);
        });
        Schema::table('channels', static function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'name', 'id']);
        });
        Schema::table('prompts', static fn(Blueprint $table) => $table->dropIndex('prompts_audio_task'));
        Schema::table('episode_assemblies', static function (Blueprint $table): void {
            $table->dropIndex(['status', 'updated_at']);
            $table->dropIndex(['status', 'started_at']);
        });
        Schema::table('publication_requests', static function (Blueprint $table): void {
            $table->dropIndex(['status', 'submitted_at']);
            $table->dropIndex(['status', 'scheduled_at']);
        });
        Schema::table('prompts', static function (Blueprint $table): void {
            $table->dropIndex(['shot_id', 'created_at', 'id']);
        });
        Schema::table('opus_clip_metrics', static function (Blueprint $table): void {
            $table->dropIndex(['observed_on', 'updated_at', 'id']);
        });
        Schema::table('publication_metric_origins', static function (Blueprint $table): void {
            $table->dropIndex('metric_origins_project_key');
        });
    }
};
