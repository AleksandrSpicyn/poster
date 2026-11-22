<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opus_sources', function (Blueprint $table): void {
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('sync_paused_at')->nullable();
            $table->string('sync_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('opus_sources', function (Blueprint $table): void {
            $table->dropColumn(['last_synced_at', 'sync_paused_at', 'sync_error']);
        });
    }
};
