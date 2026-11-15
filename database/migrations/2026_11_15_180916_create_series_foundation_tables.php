<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->json('specification');
            $table->unsignedInteger('specification_version')->default(1);
            $table->unsignedInteger('continuity_version')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('characters', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('character_revisions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('character_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('status')->default('draft');
            $table->text('description');
            $table->json('appearance');
            $table->json('voice');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['character_id', 'number']);
            $table->timestamps();
        });

        Schema::create('episodes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->foreignId('predecessor_episode_id')->nullable()->constrained('episodes')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('validity')->default('current');
            $table->unique(['series_id', 'number']);
            $table->timestamps();
        });

        Schema::create('episode_revisions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('episode_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('status')->default('draft');
            $table->string('title');
            $table->text('objective');
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('context_snapshot');
            $table->string('context_hash', 64)->nullable();
            $table->unsignedInteger('base_continuity_version')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['episode_id', 'number']);
            $table->timestamps();
        });

        Schema::create('episode_revision_characters', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('episode_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_revision_id')->constrained()->restrictOnDelete();
            $table->unique(['episode_revision_id', 'character_revision_id'], 'episode_revision_characters_unique');
        });

        Schema::create('scenes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('episode_revision_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->json('setting');
            $table->text('objective');
            $table->json('initial_state');
            $table->json('expected_final_state');
            $table->boolean('is_closed')->default(false);
            $table->unique(['episode_revision_id', 'position']);
            $table->timestamps();
        });

        Schema::create('shots', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scene_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('duration_ms');
            $table->text('action');
            $table->json('camera');
            $table->json('dialogue');
            $table->string('transition_mode')->default('new_scene');
            $table->foreignId('source_prompt_id')->nullable()->constrained('prompts')->restrictOnDelete();
            $table->json('source_anchor');
            $table->unique(['scene_id', 'position']);
            $table->timestamps();
        });

        Schema::create('episode_assemblies', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('episode_revision_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('status')->default('draft');
            $table->json('manifest');
            $table->string('manifest_hash', 64)->nullable();
            $table->json('continuity_snapshot');
            $table->unsignedInteger('lock_version')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['episode_revision_id', 'number']);
            $table->timestamps();
        });

        Schema::table('series', static function (Blueprint $table): void {
            $table->foreignId('canonical_assembly_id')->nullable()->constrained('episode_assemblies')->restrictOnDelete();
        });

        Schema::table('characters', static function (Blueprint $table): void {
            $table->foreignId('current_revision_id')->nullable()->constrained('character_revisions')->restrictOnDelete();
        });

        Schema::table('episodes', static function (Blueprint $table): void {
            $table->foreignId('current_revision_id')->nullable()->constrained('episode_revisions')->restrictOnDelete();
        });

        Schema::table('episodes', static function (Blueprint $table): void {
            $table->foreignId('accepted_assembly_id')->nullable()->constrained('episode_assemblies')->restrictOnDelete();
        });

        Schema::table('episode_revisions', static function (Blueprint $table): void {
            $table->foreignId('source_assembly_id')->nullable()->constrained('episode_assemblies')->restrictOnDelete();
        });

        Schema::table('shots', static function (Blueprint $table): void {
            $table->foreignId('source_assembly_id')->nullable()->constrained('episode_assemblies')->restrictOnDelete();
        });

        Schema::table('prompts', static function (Blueprint $table): void {
            $table->foreignId('shot_id')->nullable()->constrained('shots')->restrictOnDelete();
            $table->unsignedInteger('take_number')->nullable();
            $table->json('input_snapshot')->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->unique(['shot_id', 'take_number']);
        });
    }

    public function down(): void
    {
        Schema::table('prompts', static function (Blueprint $table): void {
            $table->dropForeign(['shot_id']);
            $table->dropUnique(['shot_id', 'take_number']);
            $table->dropColumn(['shot_id', 'take_number', 'input_snapshot', 'input_hash']);
        });

        Schema::table('shots', static function (Blueprint $table): void {
            $table->dropForeign(['source_assembly_id']);
        });

        Schema::table('episode_revisions', static function (Blueprint $table): void {
            $table->dropForeign(['source_assembly_id']);
        });

        Schema::table('episodes', static function (Blueprint $table): void {
            $table->dropForeign(['accepted_assembly_id']);
        });

        Schema::table('episodes', static function (Blueprint $table): void {
            $table->dropForeign(['current_revision_id']);
        });

        Schema::table('characters', static function (Blueprint $table): void {
            $table->dropForeign(['current_revision_id']);
        });

        Schema::table('series', static function (Blueprint $table): void {
            $table->dropForeign(['canonical_assembly_id']);
        });

        Schema::dropIfExists('episode_assemblies');

        Schema::dropIfExists('shots');

        Schema::dropIfExists('scenes');

        Schema::dropIfExists('episode_revision_characters');

        Schema::dropIfExists('episode_revisions');

        Schema::dropIfExists('episodes');

        Schema::dropIfExists('character_revisions');

        Schema::dropIfExists('characters');

        Schema::dropIfExists('series');
    }
};
