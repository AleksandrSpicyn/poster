<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_reservations', function (Blueprint $table): void {
            $table->index(['accounting_mode', 'prompt_id', 'status'], 'reservations_mode_prompt_status');
        });
    }

    public function down(): void
    {
        Schema::table('generation_reservations', function (Blueprint $table): void {
            $table->dropIndex('reservations_mode_prompt_status');
        });
    }
};
