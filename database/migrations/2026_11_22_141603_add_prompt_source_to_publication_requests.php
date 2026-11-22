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
        Schema::table('publication_requests', function (Blueprint $table): void {
            $table->foreignId('prompt_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['prompt_id', 'channel_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('publication_requests')->whereNotNull('prompt_id')->exists()) {
            throw new RuntimeException('Archive short publication receipts before rolling back.');
        }

        Schema::table('publication_requests', function (Blueprint $table): void {
            $table->dropUnique(['prompt_id', 'channel_id']);
            $table->dropConstrainedForeignId('prompt_id');
        });
    }
};
