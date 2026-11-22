<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clipping_connections', function (Blueprint $table): void {
            $table->timestamp('access_checked_at')->nullable();
            $table->json('access_result')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clipping_connections', function (Blueprint $table): void {
            $table->dropColumn(['access_checked_at', 'access_result']);
        });
    }
};
