<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', static function (Blueprint $table): void {
            $table->boolean('auto_publish')->default(true);
        });
        Schema::table('episodes', static function (Blueprint $table): void {
            $table->json('delivery_channel_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('episodes', static function (Blueprint $table): void {
            $table->dropColumn('delivery_channel_ids');
        });
        Schema::table('series', static function (Blueprint $table): void {
            $table->dropColumn('auto_publish');
        });
    }
};
