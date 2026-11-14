<?php

declare(strict_types=1);

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompts', static function (Blueprint $table): void {
            $table->id();
            $table->text('prompt');
            $table->text('image_url')->nullable();
            $table->text('audio_id')->nullable();
            $table->text('audio_url')->nullable();
            $table->text('video_id')->nullable();
            $table->text('video_url')->nullable();
            $table->boolean('generated')->default(false);
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
