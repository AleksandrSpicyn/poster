<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('состав заказа использует серверный каталог и объясняет закрытые продажи', function (): void {
    actingAs(User::factory()->create())
        ->get(route('billing.checkout', 'one_off'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('selected', 'one_off')->where('catalog.0.amount', 29000)
            ->where('catalog.0.seconds', 30)->where('checkout.blocked', 'Покупки для этого аккаунта пока закрыты.')->has('checkout.request_key'));
});

test('отсутствующий тариф возвращает 404', function (): void {
    actingAs(User::factory()->create())->get(route('billing.checkout', 'unknown'))->assertNotFound();
});
