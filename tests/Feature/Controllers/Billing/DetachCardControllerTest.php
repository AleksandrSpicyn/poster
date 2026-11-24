<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\billingOwner;

test('кабинет маскирует карту, отвязка фиксируется один раз', function (): void {
    $owner = billingOwner();
    DB::table('billing_accounts')->where('owner_id', $owner->id)->update(['payment_method_id' => 'private-card-token',
        'payment_method_last_four' => '4242', 'payment_method_provider' => 'pilot', 'payment_method_shop' => 'private-shop', 'payment_method_test_mode' => true]);

    actingAs($owner)->get(route('billing.show'))->assertDontSee('private-card-token')->assertDontSee('private-shop')
        ->assertInertia(fn(Assert $page): Assert => $page->where('account.payment_method_last_four', '4242'));
    actingAs($owner)->delete(route('billing.payment_method.destroy'))->assertRedirect(route('billing.show'));
    actingAs($owner)->delete(route('billing.payment_method.destroy'))->assertRedirect(route('billing.show'));

    assertDatabaseHas('billing_accounts', ['owner_id' => $owner->id, 'payment_method_id' => null, 'payment_method_last_four' => null, 'payment_method_shop' => null]);
    assertDatabaseHas('billing_consents', ['actor_id' => $owner->id, 'action' => 'detach', 'revision' => 1]);
    assertDatabaseCount('billing_consents', 1);
});
