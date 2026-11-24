<?php

declare(strict_types=1);

use App\Enums\BillingProduct;
use App\Notifications\BillingRenewalReminder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\travelTo;
use function Tests\Support\billingOwner;
use function Tests\Support\consoleCommand;

test('продление сохраняет день января после февраля и отправляет одно напоминание', function (bool $detach): void {
    $owner = billingOwner();
    $due = '2027-02-28 10:00:00';
    config(['app.url' => 'https://poster.example', 'billing.merchants.sandbox.shop' => 'pilot', 'billing.merchants.sandbox.credentials.secret' => 'sandbox']);
    DB::table('billing_accounts')->where('owner_id', $owner->id)->update(['current_product' => 'author', 'next_product' => 'studio', 'renews_at' => $due,
        'calendar_anchor_at' => '2027-01-31 10:00:00', 'payment_method_provider' => 'yookassa', 'payment_method_shop' => 'pilot',
        'payment_method_id' => 'saved-card', 'payment_method_test_mode' => true, 'renewal_consent' => true, 'automatic_payments_enabled' => true]);
    DB::table('billing_consents')->insert(['owner_id' => $owner->id, 'actor_id' => $owner->id, 'revision' => 0, 'action' => 'enable',
        'product_code' => 'studio', 'amount_kopecks' => 279000, 'terms_version' => BillingProduct::CATALOG_VERSION, 'created_at' => now('UTC')]);
    Notification::fake();
    Http::fake(function (Request $request) use ($due) {
        if (str_ends_with($request->url(), '/me')) {
            return Http::response(['account_id' => 'pilot', 'test' => true]);
        }

        /** @var object{id: string, order_id: int} $attempt */
        $attempt = DB::table('billing_payments')->firstOrFail();

        return Http::response(['id' => 'renewed', 'recipient' => ['account_id' => 'pilot'], 'test' => true, 'status' => 'succeeded', 'paid' => true,
            'amount' => ['value' => '2790.00', 'currency' => 'RUB'], 'captured_at' => $due . 'Z',
            'metadata' => ['attempt_id' => $attempt->id, 'order_id' => (string) $attempt->order_id]]);
    });
    travelTo(CarbonImmutable::parse($due, 'UTC')->subDays(3));
    consoleCommand('billing:process-periods')->assertSuccessful();
    consoleCommand('billing:process-periods')->assertSuccessful();
    Notification::assertSentToTimes($owner, BillingRenewalReminder::class, 1);

    travelTo(CarbonImmutable::parse($due, 'UTC'));
    consoleCommand('billing:process-periods')->assertSuccessful();
    consoleCommand('billing:process-periods')->assertSuccessful();

    if ($detach) {
        actingAs($owner)->delete(route('billing.payment_method.destroy'))->assertRedirect();
    }

    consoleCommand('billing:sync-checkouts')->assertSuccessful();
    travelTo(CarbonImmutable::parse($due, 'UTC')->addMinutes(2));
    consoleCommand('billing:sync-checkouts')->assertSuccessful();

    assertDatabaseCount('billing_orders', 1);
    assertDatabaseCount('billing_payments', 1);

    if ($detach) {
        Http::assertNothingSent();
        assertDatabaseCount('generation_grants', 0);
        assertDatabaseHas('billing_orders', ['purpose' => 'renewal', 'status' => 'cancelled']);
    } else {
        Http::assertSent(fn(Request $request): bool => $request->method() === 'POST' && $request['payment_method_id'] === 'saved-card' && ! isset($request['confirmation']));
        assertDatabaseHas('generation_grants', ['owner_id' => $owner->id, 'granted_seconds' => 360, 'ends_at' => '2027-03-31 10:00:00']);
    }
})->with([false, true]);

test('временный отказ продления разрешает один повтор через двое суток', function (string $reason, int $expected): void {
    $owner = billingOwner();
    $due = CarbonImmutable::parse('2027-05-12 07:00:00', 'UTC');
    config(['app.url' => 'https://poster.example', 'billing.merchants.sandbox.shop' => 'pilot', 'billing.merchants.sandbox.credentials.secret' => 'sandbox']);
    DB::table('billing_accounts')->where('owner_id', $owner->id)->update(['current_product' => 'author', 'renews_at' => $due, 'calendar_anchor_at' => $due->subMonth(),
        'payment_method_provider' => 'yookassa', 'payment_method_shop' => 'pilot', 'payment_method_id' => 'card', 'payment_method_test_mode' => true, 'consent_revision' => 1,
        'renewal_consent' => true, 'automatic_payments_enabled' => true]);
    DB::table('billing_consents')->insert(['owner_id' => $owner->id, 'actor_id' => $owner->id, 'revision' => 1, 'action' => 'enable', 'product_code' => 'author',
        'amount_kopecks' => 99000, 'terms_version' => BillingProduct::CATALOG_VERSION, 'created_at' => $due->subMonth()]);
    Http::fake(fn(Request $request) => str_ends_with($request->url(), '/me') ? Http::response(['account_id' => 'pilot', 'test' => true]) :
        Http::response(['id' => (string) Str::uuid(), 'status' => 'canceled', 'recipient' => ['account_id' => 'pilot'], 'test' => true,
            'amount' => $request['amount'], 'metadata' => $request['metadata'], 'cancellation_details' => ['reason' => $reason]]));

    foreach ([0, 1, 2, 3, 10] as $days) {
        travelTo($due->addDays($days));
        consoleCommand('billing:process-periods')->assertSuccessful();
        consoleCommand('billing:sync-checkouts')->assertSuccessful();

        if ($days < 2) {
            assertDatabaseCount('billing_payments', 1);
        }
    }

    assertDatabaseCount('billing_orders', 1);
    assertDatabaseCount('billing_payments', $expected);
    assertDatabaseCount('generation_grants', 0);
})->with([['insufficient_funds', 2], ['card_expired', 1]]);

test('письмо о новом тарифе учитывает сумму согласия', function (bool $currentPrice): void {
    $owner = billingOwner();
    $due = now('UTC')->addDay()->startOfSecond();
    DB::table('billing_accounts')->where('owner_id', $owner->id)->update([
        'current_product' => 'author',
        'renews_at' => $due,
        'payment_method_id' => 'saved-card',
        'automatic_payments_enabled' => true,
        'renewal_consent' => true,
    ]);
    DB::table('billing_consents')->insert([
        'owner_id' => $owner->id,
        'actor_id' => $owner->id,
        'revision' => 0,
        'action' => 'enable',
        'product_code' => 'author',
        'amount_kopecks' => $currentPrice ? BillingProduct::AUTHOR->amountKopecks() : 1,
        'terms_version' => BillingProduct::CATALOG_VERSION,
        'created_at' => now('UTC'),
    ]);
    Notification::fake();

    consoleCommand('billing:process-periods')->assertSuccessful();

    Notification::assertSentTo($owner, BillingRenewalReminder::class, fn(BillingRenewalReminder $notice): bool => $notice->automatic === $currentPrice);
})->with(['действующая сумма' => true, 'прежняя сумма' => false]);
