<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Billing\MerchantData;
use App\Data\Billing\RefundAttemptData;
use App\Data\Billing\RefundRequestData;
use App\Data\Billing\RefundResultData;
use App\Enums\GenerationCreditBucket;
use App\Enums\PaymentStatus;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnexpectedValueException;

final readonly class BillingRefundRepository
{
    public function __construct(private BillingOperatorRepository $operators, private BillingReceiptRepository $receipts, private GenerationCreditRepository $credits) {}

    public function open(string $id, string $payment, int $operator, int $amount, int $seconds, string $reason): void
    {
        if (! Str::isUuid($id) || $amount < 1 || $seconds < 0 || trim($reason) === '' || mb_strlen($reason) > 255) {
            throw new DomainException('Проверьте ключ операции, сумму, секунды и основание возврата.');
        }

        DB::transaction(function () use ($id, $payment, $operator, $amount, $seconds, $reason): void {
            $this->operators->authorize($operator);
            /** @var object{owner_id: int, order_id: int, provider: string, shop: string, test_mode: bool, amount_kopecks: int, currency: string}|null $paid */
            $paid = DB::table('billing_payments as p')->join('billing_orders as o', 'o.id', '=', 'p.order_id')->where('p.id', $payment)
                ->where('p.status', 'succeeded')->whereNotNull('p.external_id')->first(['p.*', 'o.owner_id']);

            if ($paid === null) {
                throw new DomainException('Подтверждённый платёж не найден.');
            }

            DB::table('billing_accounts')->where('owner_id', $paid->owner_id)->lockForUpdate()->firstOrFail();
            $prior = DB::table('billing_refunds')->where('id', $id)->lockForUpdate()->first();

            if ($prior !== null) {
                if ($prior->payment_id !== $payment || $prior->operator_id !== $operator || $prior->amount_kopecks !== $amount || $prior->adjustment_seconds !== $seconds || $prior->reason !== $reason) {
                    throw new DomainException('Ключ возврата уже связан с другими условиями.');
                }

                return;
            }

            $refunds = DB::table('billing_refunds')->where('payment_id', $payment)->where('status', '!=', 'cancelled');
            /** @var numeric-string|int $committed */
            $committed = $refunds->sum('amount_kopecks');

            if ($amount > $paid->amount_kopecks - (int) $committed) {
                throw new DomainException('Сумма превышает остаток платежа с учётом ожидающих возвратов.');
            }

            /** @var object{id: int, available_seconds: int, held_seconds: int, ends_at: ?string}|null $grant */
            $grant = DB::table('generation_grants')->where('order_id', $paid->order_id)->lockForUpdate()->first();
            $excess = DB::table('billing_events')->where('event_key', 'payment.excess.' . $payment)->exists();

            if ($excess) {
                $grant = null;
            }

            $available = $grant === null || ($grant->ends_at !== null && $grant->ends_at <= now('UTC')->toDateTimeString()) ? 0 : $grant->available_seconds;
            $full = $amount + (int) $committed === $paid->amount_kopecks;

            if ($seconds > $available || ($full && ($seconds !== $available || ($grant->held_seconds ?? 0) > 0 || $refunds->where('status', '!=', 'succeeded')->exists()))) {
                throw new DomainException('Проверьте свободные секунды и завершите открытые резервы перед полным возвратом.');
            }

            $at = now('UTC');
            DB::table('billing_refunds')->insert([
                'id' => $id,
                'payment_id' => $payment,
                'operator_id' => $operator,
                'adjustment_seconds' => $seconds,
                'reason' => $reason,
                'idempotency_key' => (string) Str::uuid(),
                'provider' => $paid->provider,
                'shop' => $paid->shop,
                'test_mode' => $paid->test_mode,
                'status' => 'created',
                'amount_kopecks' => $amount,
                'currency' => $paid->currency,
                'next_check_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            if ($grant !== null && $seconds > 0) {
                $this->credits->transfer($paid->owner_id, $grant->id, null, $seconds, GenerationCreditBucket::AVAILABLE, GenerationCreditBucket::HELD, 'refund_hold', 'refund.hold.' . $id, 'Возврат');
            }
        }, 3);
    }

    /** @return list<string> */
    public function due(): array
    {
        /** @var list<string> */
        return DB::table('billing_refunds')->where('next_check_at', '<=', now('UTC'))->orderBy('next_check_at')->limit(50)->pluck('id')->all();
    }

    public function notification(string $external, ?string $description): ?string
    {
        $query = DB::table('billing_refunds')->where('provider', 'yookassa')->whereIn('status', ['pending', 'unknown'])->whereNotNull('sent_at');
        /** @var ?string $known */
        $known = (clone $query)->where('external_id', $external)->value('id');

        if ($known !== null || $description === null || ! str_starts_with($description, 'Poster ')) {
            return $known;
        }

        $candidate = substr($description, 7);

        return Str::isUuid($candidate) && $query->where('id', $candidate)->whereNull('external_id')->exists() ? $candidate : null;
    }

    public function claim(string $id, ?string $external = null): ?RefundAttemptData
    {
        return DB::transaction(function () use ($id, $external): ?RefundAttemptData {
            /** @var object{external_id: ?string, provider: string, shop: string, test_mode: bool, idempotency_key: string, amount_kopecks: int, sent_at: ?string, payment_id: string}|null $row */
            $row = DB::table('billing_refunds')->where('id', $id)->whereIn('status', [
                'created',
                'unknown',
                'pending',
            ])->lockForUpdate()->first();

            if ($row === null || ($external !== null && ($row->sent_at === null || ($row->external_id !== null && $row->external_id !== $external)))) {
                return null;
            }

            $lookup = $row->external_id ?? $external;

            if ($row->sent_at !== null && $lookup === null) {
                return null;
            }

            /** @var string $payment */
            $payment = DB::table('billing_payments')->where('id', $row->payment_id)->value('external_id');
            DB::table('billing_refunds')->where('id', $id)->update([
                'sent_at' => $row->sent_at ?? now('UTC'),
                'status' => $lookup === null ? 'unknown' : 'pending',
                'next_check_at' => $lookup === null ? null : now('UTC')->addMinutes(2),
                'updated_at' => now('UTC'),
            ]);

            return new RefundAttemptData(
                $id,
                new MerchantData($row->provider, $row->shop, (bool) $row->test_mode),
                new RefundRequestData($payment, $row->amount_kopecks, $row->idempotency_key, 'Poster ' . $id),
                $lookup,
                $external !== null && $row->external_id === null,
            );
        }, 3);
    }

    public function record(RefundAttemptData $attempt, RefundResultData $result): void
    {
        if ($attempt->request->paymentId !== $result->paymentId || $attempt->request->amountKopecks !== $result->amountKopecks
            || ($attempt->externalId !== null && $attempt->externalId !== $result->externalId) || ($attempt->recovering && $attempt->request->reference !== $result->reference)) {
            throw new UnexpectedValueException('Данные возврата расходятся с локальной операцией.');
        }

        DB::transaction(function () use ($attempt, $result): void {
            /** @var object{owner_id: int, order_id: int, payment_id: string, payment_amount: int, period_ends_at: ?string} $order */
            $order = DB::table('billing_refunds as r')->join('billing_payments as p', 'p.id', '=', 'r.payment_id')->join('billing_orders as o', 'o.id', '=', 'p.order_id')
                ->where('r.id', $attempt->id)->firstOrFail([
                    'o.owner_id',
                    'p.order_id',
                    'r.payment_id',
                    'p.amount_kopecks as payment_amount',
                    'o.period_ends_at',
                ]);
            $account = DB::table('billing_accounts')->where('owner_id', $order->owner_id)->lockForUpdate()->firstOrFail();
            /** @var object{status: string, external_id: ?string, adjustment_seconds: int} $refund */
            $refund = DB::table('billing_refunds')->where('id', $attempt->id)->lockForUpdate()->firstOrFail();

            if (in_array($refund->status, ['succeeded', 'cancelled'], true)) {
                return;
            }

            if ($refund->external_id !== null && $refund->external_id !== $result->externalId) {
                throw new UnexpectedValueException('Идентификатор возврата изменился.');
            }

            $status = $attempt->externalId === null ? PaymentStatus::PENDING : $result->status;
            $finished = in_array($status, [PaymentStatus::SUCCEEDED, PaymentStatus::CANCELLED], true);
            $at = now('UTC');
            DB::table('billing_refunds')->where('id', $attempt->id)->update([
                'status' => $status->value,
                'external_id' => $result->externalId,
                'confirmed_at' => $finished ? $at : null,
                'next_check_at' => $finished ? null : $at->copy()->addMinute(),
                'updated_at' => $at,
            ]);

            if (! $finished) {
                return;
            }

            if ($refund->adjustment_seconds > 0) {
                /** @var object{id: int, ends_at: ?string} $grant */
                $grant = DB::table('generation_grants')->where('order_id', $order->order_id)->lockForUpdate()->firstOrFail();
                $to = $status === PaymentStatus::SUCCEEDED ? GenerationCreditBucket::REVOKED : ($grant->ends_at !== null && $grant->ends_at <= $at->toDateTimeString() ? GenerationCreditBucket::EXPIRED : GenerationCreditBucket::AVAILABLE);
                $this->credits->transfer($order->owner_id, $grant->id, null, $refund->adjustment_seconds, GenerationCreditBucket::HELD, $to, $status === PaymentStatus::SUCCEEDED ? 'revoke' : 'refund_release', 'refund.close.' . $attempt->id, 'Возврат');
            }

            if ($status === PaymentStatus::SUCCEEDED) {
                $this->receipts->queue($order->payment_id, $attempt->id);
                /** @var numeric-string|int $total */
                $total = DB::table('billing_refunds')->where('payment_id', $order->payment_id)->where('status', 'succeeded')->sum('amount_kopecks');
                $full = (int) $total === $order->payment_amount;
                $excess = DB::table('billing_events')->where('event_key', 'payment.excess.' . $order->payment_id);

                if ($excess->exists()) {
                    if ($full) {
                        $excess->update(['status' => 'processed', 'updated_at' => $at]);
                    }

                    if (DB::table('generation_grants')->where('order_id', $order->order_id)->exists()) {
                        return;
                    }
                }

                DB::table('billing_orders')->where('id', $order->order_id)->update(['status' => $full ? 'refunded' : 'partially_refunded', 'updated_at' => $at]);

                if ($full && ! $excess->exists() && $order->period_ends_at !== null && $account->renews_at === $order->period_ends_at) {
                    DB::table('billing_accounts')->where('owner_id', $order->owner_id)->update([
                        'renewal_consent' => false,
                        'current_product' => null,
                        'next_product' => null,
                        'calendar_anchor_at' => null,
                        'renews_at' => $at,
                        'updated_at' => $at,
                    ]);
                }
            }
        }, 3);
    }
}
