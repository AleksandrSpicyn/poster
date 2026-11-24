<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_consents', function (Blueprint $table): void {
            $table->string('product_code')->nullable();
            $table->unsignedInteger('amount_kopecks')->nullable();
        });
        Schema::table('billing_orders', function (Blueprint $table): void {
            $table->unsignedInteger('consent_revision')->nullable();
            $table->timestamp('renewal_due_at')->nullable();
            $table->string('receipt_email')->nullable();
            $table->timestamp('retry_at')->nullable()->index();
        });
        Schema::table('billing_accounts', function (Blueprint $table): void {
            $table->boolean('renewal_consent')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('billing_consents', fn(Blueprint $table) => $table->dropColumn(['product_code', 'amount_kopecks']));
        Schema::table('billing_accounts', fn(Blueprint $table) => $table->dropColumn('renewal_consent'));
        Schema::table('billing_orders', function (Blueprint $table): void {
            $table->dropIndex(['retry_at']);
            $table->dropColumn(['consent_revision', 'renewal_due_at', 'receipt_email', 'retry_at']);
        });
    }
};
