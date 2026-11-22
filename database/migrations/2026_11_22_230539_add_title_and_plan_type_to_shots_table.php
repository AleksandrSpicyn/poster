<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shots', function (Blueprint $table): void {
            $table->string('title')->nullable();
            $table->string('plan_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shots', function (Blueprint $table): void {
            $table->dropColumn(['title', 'plan_type']);
        });
    }
};
