<?php

declare(strict_types=1);

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\ProjectContentMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
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
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('content_mode')->default(ProjectContentMode::SHORT->value);
        });

        Schema::table('series', function (Blueprint $table): void {
            $table->boolean('automation_enabled')->default(false);
            $table->foreignId('automation_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('episode_limit')->nullable();
        });

        DB::table('projects')->whereExists(function (Builder $query): void {
            $query->selectRaw('1')->from('series')->whereColumn('series.project_id', 'projects.id');
        })->update(['content_mode' => ProjectContentMode::SERIES->value]);

        Schema::create('production_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('episode_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('episode_assembly_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_key', 120)->unique();
            $table->string('status')->default(ProductionStatus::QUEUED->value);
            $table->string('stage')->default(ProductionStage::PREPARING->value);
            $table->json('outcome_snapshot')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_check_at']);
            $table->index(['series_id', 'status']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_runs');

        Schema::table('series', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('automation_user_id');
            $table->dropColumn(['automation_enabled', 'episode_limit']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('content_mode');
        });
    }
};
