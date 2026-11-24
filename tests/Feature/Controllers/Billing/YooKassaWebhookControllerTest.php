<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function Tests\Support\interruptedCheckout;

/** @return array{type: string, event: string, object: array{id: string, status: string, metadata: array{attempt_id: string}}} */
function billingNotification(string $attempt, string $event = 'payment.succeeded'): array
{
    return ['type' => 'notification', 'event' => $event, 'object' => [
        'id' => 'external-payment', 'status' => 'succeeded', 'metadata' => ['attempt_id' => $attempt],
    ]];
}

test('повтор вебхука после потери ответа начисляет секунды один раз', function (): void {
    $fixture = interruptedCheckout();
    Http::fake(['*/payments/external-payment' => Http::response($fixture['receipt'])]);
    $url = route('webhook.yookassa');
    $notice = billingNotification($fixture['attempt']);
    DB::table('billing_accounts')->update(['sales_enabled' => false]);

    postJson($url, $notice)->assertOk()->assertContent('');
    postJson($url, $notice)->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET');
    assertDatabaseHas('billing_payments', ['id' => $fixture['attempt'], 'external_id' => 'external-payment', 'status' => 'succeeded']);
    assertDatabaseHas('generation_grants', ['order_id' => $fixture['order'], 'available_seconds' => 120]);
    assertDatabaseCount('generation_credit_entries', 1);
});

test('объявленный успех не начисляет секунды при отмене в магазине', function (): void {
    $fixture = interruptedCheckout();
    Http::fake(['*/payments/external-payment' => Http::response([...$fixture['receipt'], 'status' => 'canceled', 'paid' => false])]);

    postJson(route('webhook.yookassa'), billingNotification($fixture['attempt']))->assertOk();

    assertDatabaseHas('billing_orders', ['id' => $fixture['order'], 'status' => 'cancelled']);
    assertDatabaseCount('generation_grants', 0);
});

test('чужие события, попытки и магазины игнорируются до обращения к API', function (): void {
    $fixture = interruptedCheckout();
    $url = route('webhook.yookassa');

    postJson($url, billingNotification((string) Str::uuid()))->assertOk();
    postJson($url, billingNotification($fixture['attempt'], 'refund.succeeded'))->assertOk();
    DB::table('billing_payments')->where('id', $fixture['attempt'])->update(['provider' => 'another']);
    postJson($url, billingNotification($fixture['attempt']))->assertOk();

    Http::assertNothingSent();
    assertDatabaseCount('generation_grants', 0);
});

test('недоступный магазин допускает повторную доставку и не закрепляет присланный ID', function (): void {
    $fixture = interruptedCheckout();
    Http::fakeSequence('*/payments/external-payment')->pushStatus(503)->push($fixture['receipt']);
    $url = route('webhook.yookassa');
    $notice = billingNotification($fixture['attempt']);

    postJson($url, $notice)->assertServerError();
    assertDatabaseHas('billing_payments', ['id' => $fixture['attempt'], 'external_id' => null, 'status' => 'unknown']);
    postJson($url, $notice)->assertOk();

    assertDatabaseCount('generation_credit_entries', 1);
});

test('некорректный конверт не вызывает провайдера', function (): void {
    postJson(route('webhook.yookassa'), ['type' => 'notification', 'event' => 'payment.succeeded'])->assertUnprocessable();

    Http::assertNothingSent();
});
