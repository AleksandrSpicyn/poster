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
        Schema::table('clipping_connections', function (Blueprint $table): void {
            $table->timestamp('catalog_checked_at')->nullable();
            $table->string('catalog_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clipping_connections', function (Blueprint $table): void {
            $table->dropColumn(['catalog_checked_at', 'catalog_error']);
        });
    }
};
