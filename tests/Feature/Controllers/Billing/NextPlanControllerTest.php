<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\billingOrder;
use function Tests\Support\billingOwner;

test('смена следующего тарифа сохраняет оплаченный месяц и допускает отмену', function (): void {
    $owner = billingOwner();
    $order = billingOrder($owner);
    DB::table('billing_accounts')->where('owner_id', $owner->id)->update(['current_product' => 'author']);
    DB::table('generation_grants')->insert(['owner_id' => $owner->id, 'order_id' => $order, 'entitlement_key' => 'first',
        'starts_at' => now('UTC')->subDay(), 'ends_at' => now('UTC')->addWeek(), 'granted_seconds' => 120, 'available_seconds' => 120]);
    $url = route('billing.subscription.next_plan');

    actingAs($owner)->patch($url, ['product' => 'one_off'])->assertSessionHasErrors('product');
    actingAs($owner)->patch($url, ['product' => 'studio'])->assertRedirect(route('billing.show'));
    assertDatabaseHas('billing_accounts', ['owner_id' => $owner->id, 'next_product' => 'studio', 'current_product' => 'author']);

    actingAs($owner)->patch($url, ['product' => null])->assertRedirect(route('billing.show'));
    assertDatabaseHas('billing_accounts', ['owner_id' => $owner->id, 'next_product' => null]);
    assertDatabaseHas('generation_grants', ['owner_id' => $owner->id, 'available_seconds' => 120]);
});

test('без оплаченного месяца нельзя планировать смену тарифа', function (): void {
    actingAs(billingOwner())->patch(route('billing.subscription.next_plan'), ['product' => 'studio'])->assertSessionHasErrors('product');
});
