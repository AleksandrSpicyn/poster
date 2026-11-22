<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opus_clip_metrics', function (Blueprint $table): void {
            $table->string('account_id')->nullable();
        });

        Schema::create('channel_metric_syncs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
        });

        Schema::create('publication_metric_origins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->char('publication_key', 64);
            $table->string('source_type', 48);
            $table->unsignedBigInteger('source_id');
            $table->unique(['owner_id', 'publication_key', 'source_type', 'source_id'], 'publication_metric_origin');
        });

        Schema::create('publication_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opus_clip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 20);
            $table->string('account');
            $table->string('media_id', 120);
            $table->string('title');
            $table->char('publication_key', 64);
            $table->date('observed_on');
            $table->json('metrics');
            $table->timestamps();
            $table->unique(['owner_id', 'publication_key', 'observed_on'], 'publication_daily_snapshot');
            $table->index(['owner_id', 'observed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_metric_origins');
        Schema::dropIfExists('channel_metric_syncs');
        Schema::dropIfExists('publication_observations');
        Schema::table('opus_clip_metrics', function (Blueprint $table): void {
            $table->dropColumn('account_id');
        });
    }
};
