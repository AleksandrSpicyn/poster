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
        foreach (['projects', 'channels', 'chats'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('owner_id')->nullable()->constrained('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['projects', 'channels', 'chats'] as $name) {
            if (DB::table($name)->whereNotNull('owner_id')->exists()) {
                throw new RuntimeException('Archive ownership assignments before rolling back.');
            }
        }

        foreach (['projects', 'channels', 'chats'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('owner_id');
            });
        }
    }
};
