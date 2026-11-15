<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_requests', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('episode_assembly_id')->constrained()->restrictOnDelete();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('planned');
            $table->json('payload');
            $table->string('container_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unique(['episode_assembly_id', 'channel_id']);
            $table->index(['status', 'updated_at']);
            $table->timestamps();
        });

        Schema::table('posts', static function (Blueprint $table): void {
            $table->foreignId('episode_assembly_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('publication_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', static function (Blueprint $table): void {
            $table->dropForeign(['publication_request_id']);
            $table->dropForeign(['episode_assembly_id']);
            $table->dropColumn(['publication_request_id', 'episode_assembly_id']);
        });
        Schema::dropIfExists('publication_requests');
    }
};
