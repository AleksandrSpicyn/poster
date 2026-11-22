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
            $table->uuid('publication_sync_checkpoint')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('opus_sources', function (Blueprint $table): void {
            $table->dropColumn('publication_sync_checkpoint');
        });
    }
};
