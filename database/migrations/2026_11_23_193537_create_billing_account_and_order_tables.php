<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_accounts', function (Blueprint $table): void {
            $table->foreignId('owner_id')->primary()->constrained('users')->restrictOnDelete();
            $table->boolean('sales_enabled')->default(false);
            $table->boolean('automatic_payments_enabled')->default(false);
            $table->string('accounting_mode', 16)->default('legacy');
            $table->string('current_product')->nullable();
            $table->string('next_product')->nullable();
            $table->timestamp('calendar_anchor_at')->nullable();
            $table->timestamp('renews_at')->nullable()->index();
            $table->unsignedInteger('consent_revision')->default(0);
            $table->string('payment_method_provider', 48)->nullable();
            $table->string('payment_method_shop', 96)->nullable();
            $table->boolean('payment_method_test_mode')->nullable();
            $table->text('payment_method_id')->nullable();
            $table->string('payment_method_last_four', 4)->nullable();
            $table->timestamps();
        });

        Schema::create('billing_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('billing_accounts', 'owner_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision');
            $table->string('action');
            $table->string('terms_version');
            $table->timestamp('created_at');
            $table->unique(['owner_id', 'revision']);
        });

        Schema::create('billing_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('billing_accounts', 'owner_id');
            $table->string('purchase_key');
            $table->string('entitlement_key');
            $table->uuid('request_key');
            $table->string('purpose');
            $table->string('product_code');
            $table->string('catalog_version');
            $table->unsignedInteger('seconds');
            $table->unsignedInteger('amount_kopecks');
            $table->char('currency', 3);
            $table->string('status');
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamps();
            $table->unique(['owner_id', 'purchase_key']);
            $table->unique(['owner_id', 'request_key']);
            $table->unique(['owner_id', 'id']);
            $table->index(['owner_id', 'created_at', 'id']);
        });

        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('order_id')->constrained('billing_orders');
            $this->providerAttempt($table);
            $table->unique(['id', 'provider', 'shop', 'test_mode'], 'payment_merchant_unique');
            $table->unsignedBigInteger('open_order_id')->nullable()->storedAs(
                "case when status in ('created', 'pending', 'unknown') then order_id else null end",
            )->unique();
        });
        Schema::create('billing_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id');
            $this->providerAttempt($table);
            $table->foreign(['payment_id', 'provider', 'shop', 'test_mode'], 'refund_payment_merchant_fk')
                ->references(['id', 'provider', 'shop', 'test_mode'])->on('billing_payments');
            $table->unique(['id', 'payment_id'], 'refund_payment_unique');
        });
        Schema::create('billing_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('payment_id');
            $table->foreignUuid('refund_id')->nullable();
            $table->foreign(['refund_id', 'payment_id'], 'receipt_refund_payment_fk')->references(['id', 'payment_id'])->on('billing_refunds');
            $table->string('kind');
            $table->string('status');
            $table->string('provider', 48);
            $table->string('shop', 96);
            $table->boolean('test_mode');
            $table->foreign(['payment_id', 'provider', 'shop', 'test_mode'], 'receipt_payment_merchant_fk')
                ->references(['id', 'provider', 'shop', 'test_mode'])->on('billing_payments');
            $table->string('external_id')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->unique(['provider', 'shop', 'test_mode', 'external_id'], $table->getTable() . '_external_unique');
            $table->timestamps();
        });
        Schema::create('billing_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('billing_accounts', 'owner_id');
            $table->string('event_key')->unique();
            $table->string('kind');
            $table->string('status');
            $table->json('payload');
            $table->timestamp('next_check_at')->nullable();
            $table->index(['status', 'next_check_at']);
            $table->timestamps();
        });
    }

    private function providerAttempt(Blueprint $table): void
    {
        $table->string('provider', 48);
        $table->string('shop', 96);
        $table->boolean('test_mode');
        $table->uuid('idempotency_key')->unique();
        $table->string('external_id')->nullable();
        $table->unique(['provider', 'shop', 'test_mode', 'external_id'], $table->getTable() . '_external_unique');
        $table->unsignedInteger('amount_kopecks');
        $table->char('currency', 3);
        $table->string('status');
        $table->string('reason')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('confirmed_at')->nullable();
        $table->timestamp('next_check_at')->nullable();
        $table->index(['status', 'next_check_at']);
        $table->timestamps();
    }

    public function down(): void
    {
        foreach (['billing_events', 'billing_receipts', 'billing_refunds', 'billing_payments', 'billing_orders', 'billing_consents', 'billing_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
