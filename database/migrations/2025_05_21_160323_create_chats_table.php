<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', static function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id');
            $table->timestamps();

            $table->unique('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
