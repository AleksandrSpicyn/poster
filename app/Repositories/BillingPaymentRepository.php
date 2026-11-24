<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Billing\MerchantData;
use App\Data\Billing\StoredPaymentData;
use App\Enums\PaymentStatus;
use Closure;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingPaymentRepository
{
    /** @param Closure():MerchantData $chooseMerchant */
    public function prepare(int $ownerId, int $orderId, Closure $chooseMerchant): string
    {
        return DB::transaction(function () use ($ownerId, $orderId, $chooseMerchant): string {
            $account = DB::table('billing_accounts')->where('owner_id', $ownerId)->lockForUpdate()->first();
            /** @var object{status: string, amount_kopecks: int, currency: string}|null $order */
            $order = DB::table('billing_orders')->where('owner_id', $ownerId)->where('id', $orderId)->lockForUpdate()->first();

            if ($account === null || $order === null) {
                throw new AuthorizationException();
            }

            $existing = DB::table('billing_payments')->where('open_order_id', $orderId)->value('id');

            if (is_string($existing)) {
                return $existing;
            }

            if (! $account->sales_enabled || $order->status !== 'open' || $order->currency !== 'RUB' || $order->amount_kopecks < 1
                || DB::table('billing_payments')->where('order_id', $orderId)->where('status', PaymentStatus::SUCCEEDED->value)->exists()) {
                throw new DomainException('Для этого заказа нельзя создать новую платёжную попытку.');
            }

            $merchant = $chooseMerchant();
            $id = (string) Str::uuid();
            $at = now('UTC');
            DB::table('billing_payments')->insert([
                'id' => $id,
                'order_id' => $orderId,
                'provider' => $merchant->provider,
                'shop' => $merchant->shop,
                'test_mode' => $merchant->testMode,
                'amount_kopecks' => $order->amount_kopecks,
                'currency' => $order->currency,
                'idempotency_key' => (string) Str::uuid(),
                'status' => PaymentStatus::CREATED->value,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            DB::table('billing_events')->insert([
                'owner_id' => $ownerId,
                'event_key' => 'payment.prepared.' . $id,
                'kind' => 'payment.prepared',
                'status' => 'pending',
                'payload' => json_encode(['attempt_id' => $id], JSON_THROW_ON_ERROR),
                'next_check_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            return $id;
        }, 3);
    }

    public function find(string $attemptId): ?StoredPaymentData
    {
        /** @var object{id: string, order_id: int, provider: string, shop: string, test_mode: int|bool, amount_kopecks: int, currency: string, external_id: string|null}|null $row */
        $row = DB::table('billing_payments')->where('id', $attemptId)->first();

        if ($row === null) {
            return null;
        }

        return new StoredPaymentData($row->id, (string) $row->order_id, new MerchantData($row->provider, $row->shop, (bool) $row->test_mode), $row->amount_kopecks, $row->currency, $row->external_id);
    }
}
