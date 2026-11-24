<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_orders', function (Blueprint $table): void {
            $table->json('fiscal_profile')->nullable();
        });
        Schema::table('billing_refunds', function (Blueprint $table): void {
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('adjustment_seconds')->default(0);
        });
        Schema::table('billing_receipts', function (Blueprint $table): void {
            $table->string('event_key')->nullable()->unique();
            $table->json('request_snapshot')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('next_check_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('billing_orders', fn(Blueprint $table) => $table->dropColumn('fiscal_profile'));
        Schema::table('billing_refunds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('operator_id');
            $table->dropColumn('adjustment_seconds');
        });
        Schema::table('billing_receipts', function (Blueprint $table): void {
            $table->dropUnique(['event_key']);
            $table->dropIndex(['next_check_at']);
            $table->dropColumn(['event_key', 'request_snapshot', 'sent_at', 'next_check_at']);
        });
    }
};
