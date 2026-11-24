<?php

declare(strict_types=1);

use App\Enums\BillingProduct;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\billingOrder;
use function Tests\Support\billingOwner;

test('владелец включает и отзывает согласие с сохранением карты', function (): void {
    $owner = billingOwner();
    DB::table('billing_accounts')->where('owner_id', $owner->id)->update(['current_product' => 'author', 'automatic_payments_enabled' => true, 'payment_method_id' => 'card']);
    $order = billingOrder($owner);
    DB::table('generation_grants')->insert(['owner_id' => $owner->id, 'order_id' => $order, 'entitlement_key' => 'month', 'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(2), 'granted_seconds' => 120, 'available_seconds' => 120]);
    $url = route('billing.subscription.renewal');
    $data = ['catalog_version' => BillingProduct::CATALOG_VERSION, 'product' => 'author', 'enabled' => true];

    actingAs($owner)->patch($url, [...$data, 'product' => 'studio'])->assertSessionHasErrors('enabled');
    actingAs($owner)->patch($url, $data)->assertSessionHasNoErrors();
    actingAs($owner)->patch($url, $data)->assertSessionHasNoErrors();
    actingAs($owner)->patch($url, [...$data, 'enabled' => false])->assertSessionHasNoErrors();

    assertDatabaseCount('billing_consents', 2);
    assertDatabaseHas('billing_consents', ['owner_id' => $owner->id, 'revision' => 1, 'amount_kopecks' => 99000, 'action' => 'enable']);
    assertDatabaseHas('billing_accounts', ['owner_id' => $owner->id, 'payment_method_id' => 'card', 'renewal_consent' => false, 'consent_revision' => 2]);
});
