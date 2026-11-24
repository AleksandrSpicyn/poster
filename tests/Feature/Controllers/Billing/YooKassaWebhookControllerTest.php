<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function Tests\Support\consoleCommand;
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

test('уведомление восстанавливает потерянный полный возврат после проверки магазина', function (): void {
    $order = interruptedCheckout();
    $operator = User::factory()->create();
    config(['billing.operators' => [$operator->email]]);
    $id = (string) Str::uuid();
    Http::fake(['*/me' => Http::response(['account_id' => 'fixture-shop', 'test' => true]),
        '*/payments/external-payment' => Http::response($order['receipt']),
        '*/refunds' => Http::failedConnection(),
        '*/refunds/refund-external' => Http::response(['id' => 'refund-external', 'status' => 'succeeded', 'description' => 'Poster ' . $id,
            'payment_id' => 'external-payment', 'amount' => ['value' => '990.00', 'currency' => 'RUB']])]);
    consoleCommand('billing:recover-payment', ['attempt' => $order['attempt'], 'external' => 'external-payment'])->assertSuccessful();
    DB::table('billing_accounts')->update(['renewal_consent' => true]);
    consoleCommand('billing:refund', ['id' => $id, '--operator' => $operator->id, '--payment' => $order['attempt'], '--kopecks' => 99000,
        '--seconds' => 120, '--reason' => 'Подтверждённый возврат'])->assertFailed();
    assertDatabaseHas('billing_refunds', ['id' => $id, 'status' => 'unknown']);

    $event = ['type' => 'notification', 'event' => 'refund.succeeded', 'object' => ['id' => 'refund-external', 'description' => 'Poster ' . $id]];
    postJson(route('webhook.yookassa'), $event)->assertOk();
    postJson(route('webhook.yookassa'), $event)->assertOk();

    assertDatabaseHas('generation_grants', ['revoked_seconds' => 120, 'available_seconds' => 0, 'held_seconds' => 0]);
    assertDatabaseHas('billing_orders', ['id' => $order['order'], 'status' => 'refunded']);
    assertDatabaseHas('billing_accounts', ['current_product' => null, 'renewal_consent' => false]);
    assertDatabaseCount('billing_refunds', 1);
    assertDatabaseCount('generation_credit_entries', 3);
});
