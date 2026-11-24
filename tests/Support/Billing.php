<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Billing\PrepareBillingPaymentAction;
use App\Enums\BillingProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function billingOwner(bool $sales = true): User
{
    $owner = User::factory()->create();
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id, 'sales_enabled' => $sales]);

    return $owner;
}

function billingOrder(User $owner, BillingProduct $product = BillingProduct::AUTHOR): int
{
    $key = (string) Str::uuid();

    return DB::table('billing_orders')->insertGetId([
        'owner_id' => $owner->id, 'request_key' => $key, 'purchase_key' => $key,
        'entitlement_key' => $key, 'purpose' => 'purchase', 'product_code' => $product->value,
        'catalog_version' => BillingProduct::CATALOG_VERSION, 'seconds' => $product->seconds(),
        'amount_kopecks' => $product->amountKopecks(), 'currency' => 'RUB', 'status' => 'paid', 'created_at' => now(),
    ]);
}

/** @return array{order: int, attempt: string, receipt: array<string, mixed>} */
function interruptedCheckout(): array
{
    config(['billing.merchants.sandbox.shop' => 'fixture-shop', 'billing.merchants.sandbox.credentials.secret' => 'fixture-secret']);
    $owner = billingOwner();
    $order = billingOrder($owner);
    DB::table('billing_orders')->where('id', $order)->update(['status' => 'open']);
    $attempt = app(PrepareBillingPaymentAction::class)->run($owner, $order);
    DB::table('billing_payments')->where('id', $attempt)->update([
        'status' => 'unknown', 'sent_at' => now('UTC'), 'checkout_return_url' => 'https://poster.example/billing/orders/' . $order,
    ]);
    config(['billing.default_merchant' => 'production']);

    return ['order' => $order, 'attempt' => $attempt, 'receipt' => [
        'id' => 'external-payment', 'test' => true, 'recipient' => ['account_id' => 'fixture-shop'], 'paid' => true,
        'amount' => ['currency' => 'RUB', 'value' => '990.00'], 'status' => 'succeeded',
        'metadata' => ['order_id' => (string) $order, 'attempt_id' => $attempt],
    ]];
}
