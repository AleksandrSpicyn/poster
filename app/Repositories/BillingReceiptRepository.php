<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Billing\MerchantData;
use App\Data\Billing\ReceiptAttemptData;
use App\Data\Billing\ReceiptRequestData;
use App\Data\Billing\ReceiptResultData;
use App\Enums\BillingProduct;
use App\Enums\PaymentStatus;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BillingReceiptRepository
{
    public function queue(string $payment, ?string $refund = null, bool $offset = false): void
    {
        DB::transaction(function () use ($payment, $refund, $offset): void {
            /** @var object{order_id: int, amount_kopecks: int, provider: string, shop: string, test_mode: bool, product_code: string, status: string, external_id: ?string, fiscal_profile: ?string, receipt_email: ?string, period_ends_at: ?string} $row */
            $row = DB::table('billing_payments as p')->join('billing_orders as o', 'o.id', '=', 'p.order_id')->where('p.id', $payment)
                ->lockForUpdate()->firstOrFail(['p.*', 'o.product_code', 'o.fiscal_profile', 'o.receipt_email', 'o.period_ends_at']);

            if ($row->fiscal_profile === null || $row->receipt_email === null || $row->external_id === null || $row->status !== 'succeeded') {
                if ($offset) {
                    throw new DomainException('Нет подтверждённого платежа с фискальными условиями.');
                }

                return;
            }

            /** @var array{vat: int, subject: string, mode: string, tax_system: ?int} $profile */
            $profile = json_decode($row->fiscal_profile, true, flags: JSON_THROW_ON_ERROR);
            $kind = $offset ? 'offset' : ($refund === null ? 'payment' : 'refund');
            $key = $kind . '.' . ($refund ?? $payment);

            if (DB::table('billing_receipts')->where('event_key', $key)->exists()) {
                return;
            }

            $amount = $row->amount_kopecks;
            $source = $row->external_id;

            if ($refund !== null) {
                /** @var object{amount_kopecks: int, external_id: string} $returned */
                $returned = DB::table('billing_refunds')->where('id', $refund)->where('payment_id', $payment)->where('status', 'succeeded')->firstOrFail();
                $source = $returned->external_id;
                $amount = $returned->amount_kopecks;
            }

            if ($offset) {
                $grant = DB::table('generation_grants')->where('order_id', $row->order_id)->first();

                if ($profile['mode'] !== 'full_prepayment' || $grant === null || $grant->held_seconds > 0 || ($grant->available_seconds > 0 && ($grant->ends_at === null || $grant->ends_at > now('UTC')->toDateTimeString()))
                    || DB::table('billing_refunds')->where('payment_id', $payment)->whereIn('status', ['created', 'pending', 'unknown'])->exists()
                    || ! DB::table('billing_receipts')->where('event_key', 'payment.' . $payment)->where('status', 'registered')->exists()) {
                    throw new DomainException('Для зачёта нужны чек предоплаты и завершённый пакет без открытых резервов и возвратов.');
                }

                /** @var numeric-string|int $refunded */
                $refunded = DB::table('billing_refunds')->where('payment_id', $payment)->where('status', 'succeeded')->sum('amount_kopecks');
                $amount -= (int) $refunded;

                if ($amount < 1) {
                    throw new DomainException('Сумма для зачёта исчерпана.');
                }
            }

            $request = new ReceiptRequestData(
                $kind,
                $source,
                $amount,
                $row->receipt_email,
                BillingProduct::from($row->product_code)->label() . ' · заказ №' . $row->order_id,
                $profile['vat'],
                $profile['subject'],
                $offset ? 'full_payment' : $profile['mode'],
                $profile['tax_system'],
            );
            DB::table('billing_receipts')->insert(['event_key' => $key, 'payment_id' => $payment, 'refund_id' => $refund, 'kind' => $kind, 'status' => 'created',
                'provider' => $row->provider, 'shop' => $row->shop, 'test_mode' => $row->test_mode, 'idempotency_key' => (string) Str::uuid(),
                'request_snapshot' => $request->toJson(), 'next_check_at' => now('UTC'), 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
        }, 3);
    }

    /** @return list<int> */
    public function due(): array
    {
        /** @var list<int> */
        return DB::table('billing_receipts')->where('next_check_at', '<=', now('UTC'))->orderBy('next_check_at')->limit(50)->pluck('id')->all();
    }

    public function claim(int $id, bool $retry, ?string $external): ?ReceiptAttemptData
    {
        return DB::transaction(function () use ($id, $retry, $external): ?ReceiptAttemptData {
            /** @var object{status: string, external_id: ?string, idempotency_key: string, request_snapshot: ?string, sent_at: ?string, provider: string, shop: string, test_mode: bool}|null $row */
            $row = DB::table('billing_receipts')->where('id', $id)->lockForUpdate()->first();

            if ($row === null || $row->request_snapshot === null || $row->status === 'registered') {
                return null;
            }

            $key = $row->idempotency_key;
            $lookup = $external ?? $row->external_id;

            if ($external !== null && ($row->sent_at === null || ($row->external_id !== null && $row->external_id !== $external))) {
                throw new DomainException('Внешний ID не соответствует отправленной попытке.');
            }

            if ($row->status === 'cancelled') {
                if (! $retry) {
                    return null;
                }

                $key = (string) Str::uuid();
                $lookup = null;
            } elseif ($lookup === null && $row->sent_at !== null && (! $retry || $row->sent_at <= now('UTC')->subHours(23)->toDateTimeString())) {
                throw new DomainException('Нужна сверка по ID чека в исходном магазине.');
            }

            DB::table('billing_receipts')->where('id', $id)->update(['sent_at' => $row->status === 'cancelled' ? now('UTC') : ($row->sent_at ?? now('UTC')),
                'idempotency_key' => $key, 'status' => $lookup === null ? 'unknown' : 'pending', 'external_id' => $row->status === 'cancelled' ? null : $row->external_id,
                'next_check_at' => $lookup === null ? null : now('UTC')->addMinutes(2), 'updated_at' => now('UTC')]);

            return new ReceiptAttemptData($id, new MerchantData($row->provider, $row->shop, (bool) $row->test_mode), ReceiptRequestData::from($row->request_snapshot), $key, $lookup);
        }, 3);
    }

    public function record(ReceiptAttemptData $attempt, ReceiptResultData $result): void
    {
        $status = match ($result->status) {
            PaymentStatus::SUCCEEDED => 'registered',
            PaymentStatus::CANCELLED => 'cancelled',
            default => 'pending',
        };
        DB::table('billing_receipts')->where('id', $attempt->id)->where('idempotency_key', $attempt->key)->whereIn('status', ['pending', 'unknown'])
            ->update(['status' => $status, 'external_id' => $result->externalId, 'next_check_at' => $status === 'pending' ? now('UTC')->addMinute() : null, 'updated_at' => now('UTC')]);
    }
}
