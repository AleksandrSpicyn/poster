<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Tests\Support\billingOrder;
use function Tests\Support\billingOwner;

test('история открывается по прямой ссылке с фильтром и проверяет вкладку', function (): void {
    $owner = billingOwner();
    $id = billingOrder($owner);

    actingAs($owner)->get(route('billing.history'))->assertInertia(fn(Assert $page): Assert => $page->where('history.data.0.id', $id));
    actingAs($owner)->get(route('billing.history', ['tab' => 'seconds', 'project' => 42]))
        ->assertInertia(fn(Assert $page): Assert => $page->where('section', 'seconds')->where('projectFilter', 42)->has('history.data', 0));
    actingAs($owner)->getJson(route('billing.history', ['tab' => 'plans']))->assertUnprocessable();
});

test('обновление баланса в истории не выбирает заказы повторно', function (): void {
    $owner = billingOwner();
    billingOrder($owner);
    $response = actingAs($owner)->get(route('billing.history'));

    DB::enableQueryLog();

    try {
        $response->assertInertia(fn(Assert $page): Assert => $page
            ->reloadOnly('balance', fn(Assert $partial): Assert => $partial
                ->where('balance.available', 0)
                ->missing('history')));
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect(collect($queries)->pluck('query')->implode(' '))->not->toMatch('/from [`"]billing_orders[`"]/i');
});
