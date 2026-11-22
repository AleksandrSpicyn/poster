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
        Schema::table('projects', static function (Blueprint $table): void {
            $table->boolean('schedule_enabled')->nullable();
            $table->string('schedule_time', 5)->nullable();
            $table->string('schedule_timezone', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', static function (Blueprint $table): void {
            $table->dropColumn(['schedule_enabled', 'schedule_time', 'schedule_timezone']);
        });
    }
};
