<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\consoleCommand;
use function Tests\Support\interruptedCheckout;

test('оператор восстанавливает платёж через исходный магазин после потери уведомления', function (): void {
    $fixture = interruptedCheckout();
    Http::fake(['*/payments/external-payment' => Http::response($fixture['receipt'])]);
    $arguments = ['attempt' => $fixture['attempt'], 'external' => 'external-payment'];

    consoleCommand('billing:recover-payment', $arguments)->expectsOutput('succeeded')->assertSuccessful();
    consoleCommand('billing:recover-payment', $arguments)->assertFailed();

    Http::assertSentCount(1);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET');
    assertDatabaseHas('billing_orders', ['id' => $fixture['order'], 'status' => 'paid']);
    assertDatabaseCount('generation_grants', 1);
});

test('ошибочный внешний ID или сумма не меняют неизвестный платёж', function (array $change): void {
    $fixture = interruptedCheckout();
    Http::fake(['*/payments/external-payment' => Http::response(array_replace_recursive($fixture['receipt'], $change))]);

    consoleCommand('billing:recover-payment', ['attempt' => $fixture['attempt'], 'external' => 'external-payment'])->assertFailed();

    assertDatabaseHas('billing_payments', ['id' => $fixture['attempt'], 'external_id' => null, 'status' => 'unknown']);
    assertDatabaseCount('generation_credit_entries', 0);
})->with([
    'другой объект' => [['id' => 'different-payment']],
    'другой заказ' => [['metadata' => ['order_id' => '9999']]],
    'другая сумма' => [['amount' => ['value' => '1.00']]],
    'боевой магазин' => [['test' => false]],
]);
