<?php

declare(strict_types=1);

use App\Enums\BillingProduct;
use App\Events\WorkspaceUpdated;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\billingOwner;
use function Tests\Support\consoleCommand;
use function Tests\Support\interruptedCheckout;

beforeEach(function (): void {
    config(['app.url' => 'https://poster.example', 'billing.merchants.sandbox.shop' => 'pilot', 'billing.merchants.sandbox.credentials.secret' => 'test-only']);
});

/** @return array{request_key: string, catalog_version: string} */
function checkoutIntent(): array
{
    return ['request_key' => (string) Str::uuid(), 'catalog_version' => BillingProduct::CATALOG_VERSION];
}

test('закрытые продажи и боевой режим блокируют создание платежа', function (bool $sales): void {
    $owner = billingOwner($sales);
    config(['billing.merchants.sandbox.test_mode' => ! $sales]);

    actingAs($owner)->post(route('billing.orders.store', 'author'), checkoutIntent())->assertSessionHasErrors('checkout');

    assertDatabaseCount('billing_orders', 0);
    Http::assertNothingSent();
})->with([false, true]);

test('устаревший каталог отклоняется до создания заказа', function (): void {
    actingAs(billingOwner())->post(route('billing.orders.store', 'studio'), [...checkoutIntent(), 'catalog_version' => 'old'])->assertSessionHasErrors('catalog_version');

    assertDatabaseCount('billing_orders', 0);
});

test('покупка проводит одну оплату и одно начисление после сверки', function (string $product, int $seconds, string $price): void {
    $owner = billingOwner();
    $intent = checkoutIntent();
    $url = route('billing.orders.store', $product);
    $reply = ['id' => 'pay-1', 'recipient' => ['account_id' => 'pilot'], 'test' => true, 'amount' => ['currency' => 'RUB', 'value' => $price]];
    Http::fake(function (Request $request) use ($reply) {
        if (str_ends_with($request->url(), '/me')) {
            return Http::response(['account_id' => 'pilot', 'test' => true]);
        }

        if ($request->method() === 'POST') {
            return Http::response([...$reply, 'metadata' => $request['metadata'], 'status' => 'pending', 'confirmation' => ['confirmation_url' => 'https://bank.example/confirm']]);
        }

        /** @var object{id: string, order_id: int} $payment */
        $payment = DB::table('billing_payments')->firstOrFail();

        return Http::response([...$reply, 'status' => 'succeeded', 'paid' => true,
            'metadata' => ['attempt_id' => $payment->id, 'order_id' => (string) $payment->order_id]]);
    });

    actingAs($owner)->post($url, [...$intent, 'amount_kopecks' => 1, 'seconds' => 999])->assertRedirect();
    $id = DB::table('billing_orders')->value('id');
    actingAs($owner)->post($url, $intent)->assertRedirect(route('billing.orders.show', $id));
    actingAs($owner)->get(route('billing.orders.show', $id))->assertInertia(fn(Assert $page): Assert => $page
        ->where('confirmationUrl', 'https://bank.example/confirm')->where('order.seconds', $seconds));
    Http::assertSentCount(2);
    assertDatabaseCount('generation_grants', 0);

    Event::fake([WorkspaceUpdated::class]);
    DB::table('billing_payments')->update(['next_check_at' => now('UTC')->subMinute()]);
    consoleCommand('billing:sync-checkouts')->assertSuccessful();
    consoleCommand('billing:sync-checkouts')->assertSuccessful();
    actingAs($owner)->post($url, $intent)->assertRedirect();
    actingAs($owner)->post($url, checkoutIntent())->assertSessionHasErrors('checkout');

    Http::assertSentCount(3);
    Event::assertDispatched(WorkspaceUpdated::class, fn(WorkspaceUpdated $event): bool => $event->broadcastWith()['resource'] === 'GenerationBalance'
        && $event->broadcastOn()[0]->name === 'private-workspace.' . $owner->id);
    assertDatabaseCount('generation_credit_entries', 1);
    assertDatabaseHas('generation_grants', ['owner_id' => $owner->id, 'order_id' => $id, 'available_seconds' => $seconds]);
    assertDatabaseHas('billing_orders', ['id' => $id, 'status' => 'paid']);
})->with([['one_off', 30, '290.00'], ['author', 120, '990.00']]);

test('потеря ответа не повторяет списание и не начисляет секунды', function (): void {
    Http::fake(['*/me' => Http::response(['account_id' => 'pilot', 'test' => true]), '*/payments' => Http::response([], 503)]);
    $owner = billingOwner();

    actingAs($owner)->post(route('billing.orders.store', 'studio'), checkoutIntent())->assertRedirect();
    DB::table('billing_payments')->update(['next_check_at' => now('UTC')->subMinute()]);
    consoleCommand('billing:sync-checkouts')->assertSuccessful();
    actingAs($owner)->post(route('billing.orders.store', 'one_off'), checkoutIntent())->assertRedirect();

    assertDatabaseHas('billing_payments', ['status' => 'unknown']);
    assertDatabaseCount('billing_orders', 1);
    assertDatabaseCount('generation_grants', 0);
    Http::assertSentCount(2);
});

test('отказ банка разрешает новую попытку первой покупки', function (): void {
    $owner = billingOwner();
    Http::fake(fn(Request $request) => str_ends_with($request->url(), '/me')
        ? Http::response(['account_id' => 'pilot', 'test' => true])
        : Http::response(['id' => (string) Str::uuid(), 'status' => 'canceled', 'test' => true, 'recipient' => ['account_id' => 'pilot'],
            'amount' => $request['amount'], 'metadata' => $request['metadata']]));

    actingAs($owner)->post(route('billing.orders.store', 'one_off'), checkoutIntent())->assertRedirect();
    actingAs($owner)->post(route('billing.orders.store', 'one_off'), checkoutIntent())->assertRedirect();

    assertDatabaseCount('billing_orders', 2);
    assertDatabaseCount('generation_grants', 0);
    expect(DB::table('billing_orders')->where('status', 'cancelled')->count())->toBe(2);
});

test('мгновенный успех проходит независимую проверку суммы перед выдачей', function (): void {
    $owner = billingOwner();
    $reply = ['id' => 'instant', 'status' => 'succeeded', 'paid' => true, 'test' => true, 'recipient' => ['account_id' => 'pilot']];
    Http::fake(function (Request $request) use ($reply) {
        if (str_ends_with($request->url(), '/me')) {
            return Http::response(['account_id' => 'pilot', 'test' => true]);
        }

        if ($request->method() === 'POST') {
            return Http::response([...$reply, 'metadata' => $request['metadata'], 'amount' => $request['amount']]);
        }

        /** @var object{id: string, order_id: int} $attempt */
        $attempt = DB::table('billing_payments')->firstOrFail();

        return Http::response([...$reply, 'metadata' => ['order_id' => (string) $attempt->order_id, 'attempt_id' => $attempt->id],
            'amount' => ['currency' => 'RUB', 'value' => '1.00']]);
    });

    actingAs($owner)->post(route('billing.orders.store', 'author'), checkoutIntent())->assertRedirect();
    assertDatabaseHas('billing_payments', ['external_id' => 'instant', 'status' => 'pending']);
    assertDatabaseCount('generation_grants', 0);
    DB::table('billing_payments')->update(['next_check_at' => now('UTC')->subMinute()]);
    consoleCommand('billing:sync-checkouts')->assertSuccessful();

    Http::assertSentCount(3);
    assertDatabaseCount('generation_grants', 0);
    assertDatabaseHas('billing_orders', ['status' => 'open']);
});

test('остановка продаж до отправки закрывает заказ без обращения в магазин', function (): void {
    $fixture = interruptedCheckout();
    DB::table('billing_payments')->where('id', $fixture['attempt'])->update(['sent_at' => null, 'status' => 'created', 'next_check_at' => now('UTC')]);
    DB::table('billing_accounts')->update(['sales_enabled' => false]);

    consoleCommand('billing:sync-checkouts')->assertSuccessful();

    assertDatabaseHas('billing_payments', ['id' => $fixture['attempt'], 'reason' => 'sales_closed', 'status' => 'cancelled', 'sent_at' => null]);
    assertDatabaseHas('billing_orders', ['id' => $fixture['order'], 'status' => 'cancelled']);
    assertDatabaseCount('generation_grants', 0);
    Http::assertNothingSent();
});

test('способ оплаты привязывается только после сверки согласия и подтверждения магазина', function (bool $consent, bool $saved, bool $withdraw): void {
    $owner = billingOwner();
    DB::table('billing_accounts')->where('owner_id', $owner->id)->update(['automatic_payments_enabled' => true]);
    Http::fake(function (Request $request) use ($saved) {
        if (str_ends_with($request->url(), '/me')) {
            return Http::response(['account_id' => 'pilot', 'test' => true]);
        }

        /** @var object{id: string, order_id: int} $attempt */
        $attempt = DB::table('billing_payments')->firstOrFail();

        return Http::response(['id' => 'card-payment', 'recipient' => ['account_id' => 'pilot'], 'test' => true, 'amount' => ['currency' => 'RUB', 'value' => '990.00'],
            'status' => $request->method() === 'POST' ? 'pending' : 'succeeded', 'paid' => true,
            'payment_method' => ['saved' => $saved, 'id' => 'private-token', 'card' => ['last4' => '4242']],
            'metadata' => ['attempt_id' => $attempt->id, 'order_id' => (string) $attempt->order_id]]);
    });
    actingAs($owner)->post(route('billing.orders.store', 'author'), [...checkoutIntent(), 'auto_renew' => $consent])->assertSessionHasNoErrors();

    if ($withdraw) {
        actingAs($owner)->delete(route('billing.payment_method.destroy'))->assertSessionHasNoErrors();
    }

    DB::table('billing_payments')->update(['next_check_at' => now('UTC')]);
    consoleCommand('billing:sync-checkouts')->assertSuccessful();
    $linked = $consent && $saved && ! $withdraw;

    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST' && ($request['save_payment_method'] ?? false) === $consent);
    assertDatabaseHas('billing_accounts', ['renewal_consent' => $linked, 'payment_method_id' => $linked ? 'private-token' : null]);
    assertDatabaseHas('generation_grants', ['owner_id' => $owner->id, 'available_seconds' => 120]);
    actingAs($owner)->get(route('billing.show'))->assertInertia(fn(Assert $page): Assert => $page->missing('account.payment_method_id'));
})->with([[false, true, false], [true, true, false], [true, false, false], [true, true, true]]);
