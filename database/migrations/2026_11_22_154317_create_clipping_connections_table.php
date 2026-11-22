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
        Schema::create('clipping_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('driver', 32);
            $table->text('api_key')->nullable();
            $table->boolean('uses_server_key')->default(false);
            $table->timestamps();
        });

        Schema::create('clipping_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clipping_connection_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('external_id');
            $table->string('source_name')->nullable();
            $table->string('game')->nullable();
            $table->json('clips');
            $table->unsignedInteger('clip_count');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('clipping_connection_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('opus_sources', function (Blueprint $table): void {
            $table->foreignId('clipping_connection_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['clipping_connection_id', 'external_id'], 'opus_connection_source');
            $table->index('project_id', 'opus_source_project');
        });
        Schema::table('opus_sources', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'external_id']);
        });

        DB::transaction(function (): void {
            foreach (DB::table('projects')->where('clipping_workspace', 'opusclip')->whereNotNull('owner_id')->get() as $project) {
                $id = DB::table('clipping_connections')->insertGetId([
                    'owner_id' => $project->owner_id, 'name' => 'OpusClip', 'driver' => 'opusclip', 'uses_server_key' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('projects')->where('id', $project->id)->update(['clipping_connection_id' => $id]);
                DB::table('opus_sources')->where('project_id', $project->id)->update(['clipping_connection_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('clipping_connections')->where('uses_server_key', false)->exists()) {
            throw new RuntimeException('Export configured clipping connections and their source mappings before rollback.');
        }

        Schema::table('opus_sources', function (Blueprint $table): void {
            $table->unique(['project_id', 'external_id']);
            $table->dropUnique('opus_connection_source');
            $table->dropIndex('opus_source_project');
        });

        foreach (['opus_sources', 'projects'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('clipping_connection_id');
            });
        }

        Schema::dropIfExists('clipping_imports');
        Schema::dropIfExists('clipping_connections');
    }
};
