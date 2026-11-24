<?php

declare(strict_types=1);

namespace Tests\Support;

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
