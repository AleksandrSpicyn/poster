<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\get;
use function Tests\Support\billingOrder;
use function Tests\Support\billingOwner;

test('пустой кабинет открывается без создания финансового аккаунта', function (): void {
    $owner = User::factory()->create();

    get(route('billing.show'))->assertRedirect(route('login'));
    actingAs(User::factory()->unverified()->create())->get(route('billing.show'))->assertRedirect(route('verification.notice'));
    actingAs($owner)->get(route('billing.show'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('Billing/Workspace')->where('section', 'overview')
            ->where('account', null)->has('catalog', 3)->has('sources', 0)->where('balance.available', fn(mixed $value): bool => is_numeric($value) && (int) $value === 0));

    assertDatabaseCount('billing_accounts', 0);
});

test('истёкшие секунды исключены из остатка, старый резерв сохранён', function (): void {
    $owner = billingOwner();

    foreach ([$owner, billingOwner()] as $account) {
        $order = billingOrder($account);
        DB::table('generation_grants')->insert(['owner_id' => $account->id, 'order_id' => $order, 'entitlement_key' => 'month',
            'starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonth(), 'granted_seconds' => 120, 'available_seconds' => 90, 'held_seconds' => 10, 'consumed_seconds' => 20]);
    }

    actingAs($owner)->get(route('billing.show'))
        ->assertInertia(fn(Assert $page): Assert => $page->has('sources', 1)->where('balance.available', fn(mixed $v): bool => is_numeric($v) && (int) $v === 0)
            ->where('balance.held', fn(mixed $v): bool => is_numeric($v) && (int) $v === 10)->where('balance.expired', fn(mixed $v): bool => is_numeric($v) && (int) $v === 90));
});

test('денежная история разделяет владельцев и страницы', function (): void {
    $owner = billingOwner();
    $oldest = billingOrder($owner);

    for ($i = 0; $i < 15; $i++) {
        billingOrder($owner);
    }

    billingOrder(billingOwner());

    actingAs($owner)->get(route('billing.show', ['section' => 'payments', 'page' => 2]))
        ->assertInertia(fn(Assert $page): Assert => $page->has('history.data', 1)->where('history.data.0.id', $oldest)->where('history.last', 2));
});
