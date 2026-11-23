<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

test('существующий владелец не получает заказ или пакет автоматически', function (): void {
    $owner = User::factory()->create();

    assertDatabaseCount('billing_accounts', 0);
    assertDatabaseCount('billing_orders', 0);
    assertDatabaseCount('generation_grants', 0);

    DB::table('billing_accounts')->insert(['owner_id' => $owner->id]);

    assertDatabaseHas('billing_accounts', ['owner_id' => $owner->id, 'sales_enabled' => false, 'automatic_payments_enabled' => false, 'accounting_mode' => 'legacy']);
});

test('право одной покупки выдаётся один раз даже при замене заказа', function (): void {
    $owner = User::factory()->create();
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id]);
    $grant = billingSchemaGrant($owner->id);
    DB::table('generation_grants')->insert($grant);
    $replacement = billingSchemaGrant($owner->id);

    expect(fn() => DB::table('generation_grants')->insert($replacement))->toThrow(QueryException::class);
    expect(fn() => DB::table('generation_grants')->insert([...$grant, 'entitlement_key' => 'different']))->toThrow(QueryException::class);
    assertDatabaseCount('generation_grants', 1);
});

test('пакет сохраняет сумму счётчиков и не допускает отрицательный остаток', function (): void {
    $owner = User::factory()->create();
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id]);
    $grant = billingSchemaGrant($owner->id);
    $query = DB::table('generation_grants')->where('id', DB::table('generation_grants')->insertGetId($grant));

    expect(fn() => $query->update(['available_seconds' => 31]))->toThrow(QueryException::class);
    expect(fn() => $query->update(['available_seconds' => -1, 'held_seconds' => 31]))->toThrow(QueryException::class);
    expect($query->update(['available_seconds' => 22, 'consumed_seconds' => 8]))->toBe(1);
});

test('часть резерва нельзя привязать к чужому пакету или резерву', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    DB::table('billing_accounts')->insert([['owner_id' => $owner->id], ['owner_id' => $other->id]]);
    $grant = DB::table('generation_grants')->insertGetId(billingSchemaGrant($other->id));
    $reservation = DB::table('generation_reservations')->insertGetId(['owner_id' => $owner->id, 'intent_key' => 'episode', 'request_hash' => hash('sha256', 'episode'), 'accounting_mode' => 'enforced', 'status' => 'held', 'quote_snapshot' => '{}']);
    $part = ['reservation_id' => $reservation, 'grant_id' => $grant, 'take_key' => 'shot-1', 'seconds' => 8, 'status' => 'held'];

    expect(fn() => DB::table('generation_reservation_parts')->insert(['owner_id' => $owner->id, ...$part]))->toThrow(QueryException::class);
    expect(fn() => DB::table('generation_reservation_parts')->insert(['owner_id' => $other->id, ...$part]))->toThrow(QueryException::class);
});

test('открытая попытка оплаты исключает вторую до конечного состояния', function (): void {
    $owner = User::factory()->create();
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id]);
    $grant = billingSchemaGrant($owner->id);
    $payment = ['order_id' => $grant['order_id'], 'provider' => 'yookassa', 'test_mode' => true, 'shop' => 'test', 'amount_kopecks' => 29_000, 'currency' => 'RUB', 'status' => 'unknown'];
    $id = (string) Str::uuid();
    DB::table('billing_payments')->insert([...$payment, 'id' => $id, 'idempotency_key' => (string) Str::uuid()]);
    $retry = [...$payment, 'id' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid()];

    expect(fn() => DB::table('billing_payments')->insert($retry))->toThrow(QueryException::class);
    DB::table('billing_payments')->where('id', $id)->update(['status' => 'cancelled']);

    expect(DB::table('billing_payments')->insert($retry))->toBeTrue();
});

/** @return array{owner_id: int, order_id: int, entitlement_key: string, starts_at: string, granted_seconds: int, available_seconds: int} */
function billingSchemaGrant(int $ownerId): array
{
    $order = DB::table('billing_orders')->insertGetId([
        'owner_id' => $ownerId, 'purchase_key' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
        'entitlement_key' => 'first-purchase', 'product_code' => 'one_off', 'purpose' => 'one_off',
        'catalog_version' => '2026-11-23', 'seconds' => 30, 'amount_kopecks' => 29_000, 'currency' => 'RUB', 'status' => 'paid',
    ]);

    return ['owner_id' => $ownerId, 'order_id' => $order, 'entitlement_key' => 'first-purchase', 'starts_at' => '2026-11-23 00:00:00', 'granted_seconds' => 30, 'available_seconds' => 30];
}

test('повтор финансового события не создаёт вторую проводку', function (): void {
    $owner = User::factory()->create();
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id]);
    $grant = DB::table('generation_grants')->insertGetId(billingSchemaGrant($owner->id));
    $ledger = DB::table('generation_credit_entries');
    $entry = ['owner_id' => $owner->id, 'grant_id' => $grant, 'event_key' => 'credit', 'kind' => 'grant', 'granted_delta' => 30, 'available_delta' => 30, 'created_at' => now()];
    $ledger->insert($entry);

    expect(fn() => $ledger->insert([...$entry, 'available_delta' => 1]))->toThrow(QueryException::class);
    assertDatabaseHas('generation_credit_entries', ['event_key' => 'credit', 'available_delta' => 30]);
});

test('возврат и чек закреплены за провайдером, магазином и режимом платежа', function (): void {
    $owner = User::factory()->create();
    DB::table('billing_accounts')->insert(['owner_id' => $owner->id]);
    $order = billingSchemaGrant($owner->id);
    $paymentId = (string) Str::uuid();
    $merchant = ['provider' => 'yookassa', 'shop' => 'pilot', 'test_mode' => true];
    $amount = ['amount_kopecks' => 100, 'currency' => 'RUB', 'status' => 'succeeded'];
    DB::table('billing_payments')->insert([...$merchant, ...$amount, 'order_id' => $order['order_id'], 'id' => $paymentId, 'idempotency_key' => (string) Str::uuid()]);
    $refund = [...$merchant, ...$amount, 'payment_id' => $paymentId, 'id' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid()];
    $receipt = [...$merchant, 'payment_id' => $paymentId, 'kind' => 'payment', 'status' => 'pending', 'idempotency_key' => (string) Str::uuid()];

    expect(fn() => DB::table('billing_refunds')->insert([...$refund, 'provider' => 'other']))->toThrow(QueryException::class);
    expect(fn() => DB::table('billing_refunds')->insert([...$refund, 'shop' => 'other']))->toThrow(QueryException::class);
    expect(fn() => DB::table('billing_receipts')->insert([...$receipt, 'test_mode' => false]))->toThrow(QueryException::class);
    expect(DB::table('billing_refunds')->insert($refund))->toBeTrue();
    expect(DB::table('billing_receipts')->insert([...$receipt, 'refund_id' => $refund['id'], 'kind' => 'refund']))->toBeTrue();
});
