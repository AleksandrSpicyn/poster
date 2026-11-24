<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_payments', function (Blueprint $table): void {
            $table->text('checkout_return_url')->nullable();
            $table->text('confirmation_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing_payments', function (Blueprint $table): void {
            $table->dropColumn(['checkout_return_url', 'confirmation_url']);
        });
    }
};
