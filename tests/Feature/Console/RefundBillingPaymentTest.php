<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\consoleCommand;
use function Tests\Support\interruptedCheckout;

test('возврат резервирует согласованные секунды и повтор чека не меняет деньги', function (): void {
    $checkout = interruptedCheckout();
    $operator = User::factory()->create();
    config(['billing.operators' => [$operator->email]]);
    DB::table('billing_orders')->where('id', $checkout['order'])->update(['receipt_email' => $operator->email,
        'fiscal_profile' => json_encode(['mode' => 'full_payment', 'subject' => 'service', 'vat' => 1, 'tax_system' => null], JSON_THROW_ON_ERROR)]);
    $refund = (string) Str::uuid();
    $args = ['id' => $refund, '--payment' => $checkout['attempt'], '--operator' => $operator->id, '--kopecks' => 49000, '--seconds' => 17, '--reason' => 'По обращению владельца'];
    $refundReceipts = 0;
    Http::fake(function (Request $request) use ($checkout, &$refundReceipts) {
        if (str_ends_with($request->url(), '/me')) {
            return Http::response(['account_id' => 'fixture-shop', 'test' => true]);
        }

        if (str_contains($request->url(), '/refunds')) {
            return Http::response(['id' => 'returned', 'payment_id' => 'external-payment', 'status' => 'succeeded', 'amount' => ['value' => '490.00', 'currency' => 'RUB']]);
        }

        if (str_ends_with($request->url(), '/receipts')) {
            return Http::response([...$request->data(), 'id' => ($request['type'] === 'refund' ? 'fiscal-refund' : 'fiscal-payment'),
                'status' => $request['type'] === 'refund' && ++$refundReceipts === 1 ? 'canceled' : 'succeeded']);
        }

        return Http::response($checkout['receipt']);
    });
    consoleCommand('billing:recover-payment', ['attempt' => $checkout['attempt'], 'external' => 'external-payment'])->assertSuccessful();
    consoleCommand('billing:refund', [...$args, '--operator' => User::factory()->create()->id])->assertFailed();
    consoleCommand('billing:refund', $args)->assertSuccessful();
    assertDatabaseHas('generation_grants', ['held_seconds' => 17, 'available_seconds' => 103]);
    consoleCommand('billing:refund', [...$args, 'id' => (string) Str::uuid(), '--kopecks' => 50001])->assertFailed();
    consoleCommand('billing:refund', $args)->assertSuccessful();
    consoleCommand('billing:refund', $args)->assertSuccessful();
    consoleCommand('billing:receipts')->assertSuccessful();
    assertDatabaseHas('billing_receipts', ['kind' => 'refund', 'status' => 'cancelled']);
    /** @var int $receiptId */
    $receiptId = DB::table('billing_receipts')->where('kind', 'refund')->value('id');
    consoleCommand('billing:receipts', ['id' => $receiptId, '--retry' => true])->assertSuccessful();
    consoleCommand('billing:receipts')->assertSuccessful();

    assertDatabaseCount('billing_payments', 1);
    assertDatabaseCount('billing_refunds', 1);
    assertDatabaseCount('billing_receipts', 2);
    assertDatabaseHas('billing_receipts', ['kind' => 'refund', 'status' => 'registered']);
    assertDatabaseHas('billing_orders', ['status' => 'partially_refunded']);
    assertDatabaseHas('generation_grants', ['held_seconds' => 0, 'available_seconds' => 103, 'revoked_seconds' => 17]);
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/receipts') && ($request['refund_id'] ?? null) === 'returned');
});
