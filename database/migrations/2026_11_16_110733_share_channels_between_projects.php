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
        Schema::create('channel_project', function (Blueprint $table): void {
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->primary(['channel_id', 'project_id']);
        });

        DB::table('channel_project')->insertUsing(['channel_id', 'project_id'], DB::table('channels')->select('id', 'project_id')->whereNotNull('project_id'));

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        if (DB::table('channels')->whereNull('project_id')->exists()
            || DB::table('channels')->count() !== DB::table('channel_project')->count()
            || DB::table('channel_project')->join('channels', 'channels.id', '=', 'channel_project.channel_id')
                ->whereColumn('channel_project.project_id', '!=', 'channels.project_id')->exists()) {
            throw new RuntimeException('Rollback would lose shared channel assignments. Restore the previous assignments before rolling back.');
        }

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->dropColumn('is_active');
        });
        Schema::dropIfExists('channel_project');
    }
};
