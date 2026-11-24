<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('publication_requests', function (Blueprint $table): void {
            $table->string('provider_phase')->nullable();
            $table->text('upload_url')->nullable();
            $table->timestamp('finish_requested_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publication_requests', function (Blueprint $table): void {
            $table->dropColumn(['provider_phase', 'upload_url', 'finish_requested_at']);
        });
    }
};
