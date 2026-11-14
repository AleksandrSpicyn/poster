<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Prompt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('external_id')->nullable();
            $table->text('url')->nullable();
            $table->foreignIdFor(Channel::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Prompt::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
