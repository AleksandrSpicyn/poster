<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('channels', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->json('credentials')->nullable();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
