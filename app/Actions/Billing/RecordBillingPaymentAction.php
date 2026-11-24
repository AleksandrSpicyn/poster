<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Data\Billing\CheckoutAttemptData;
use App\Data\Billing\PaymentResultData;
use App\Enums\BillingProduct;
use App\Enums\PaymentStatus;
use App\Repositories\BillingCheckoutRepository;
use App\Repositories\BillingReceiptRepository;
use App\Repositories\BillingSettlementRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final readonly class RecordBillingPaymentAction
{
    public function __construct(private BillingCheckoutRepository $checkout, private BillingSettlementRepository $settlements, private BillingReceiptRepository $receipts) {}

    public function run(CheckoutAttemptData $attempt, PaymentResultData $result, bool $restore = false): void
    {
        $request = $attempt->request;

        if ($result->attemptId !== $request->attemptId || $result->orderId !== (string) $request->orderId || $result->amountKopecks !== $request->amountKopecks
            || ($attempt->externalId !== null && $result->externalId !== $attempt->externalId)) {
            throw new UnexpectedValueException('Ответ провайдера расходится с заказом.');
        }

        $owner = $this->settlements->ownerForOrder($request->orderId);

        DB::transaction(function () use ($owner, $request, $result, $attempt, $restore): void {
            $account = $this->settlements->lockAccount($owner);
            $order = $this->settlements->lockOrder($request->orderId);
            $active = $this->checkout->lockOpenAttempt($request->attemptId);

            if ($active === null) {
                if ($restore && $result->status === PaymentStatus::SUCCEEDED && $order->status === 'paid' && $order->period_starts_at !== null
                    && ! $this->settlements->hasEntitlement($owner, $order->entitlement_key)
                    && ! $this->settlements->hasActiveRefund($request->attemptId)) {
                    $this->settlements->grant(
                        $owner,
                        $order->id,
                        $order->entitlement_key,
                        $order->seconds,
                        CarbonImmutable::parse($order->period_starts_at, 'UTC'),
                        $order->period_ends_at === null ? null : CarbonImmutable::parse($order->period_ends_at, 'UTC'),
                    );
                }

                return;
            }

            if ($active->external_id !== null && $active->external_id !== $result->externalId) {
                throw new UnexpectedValueException('Внешний идентификатор платежа изменился.');
            }

            $at = CarbonImmutable::now('UTC');
            // Save the external ID first. A successful creation response is reconciled by a separate provider read.
            $status = $result->status === PaymentStatus::SUCCEEDED && $attempt->externalId === null ? PaymentStatus::PENDING : $result->status;
            $paid = $status === PaymentStatus::SUCCEEDED;
            $finished = $paid || $status === PaymentStatus::CANCELLED;
            $this->settlements->recordAttempt($request->attemptId, $result, $status, $active->confirmation_url, $finished, $at);

            if (! $finished) {
                return;
            }

            if (in_array($order->status, [
                'paid',
                'partially_refunded',
                'refunded',
            ], true)) {
                if ($paid) {
                    $this->receipts->queue($request->attemptId);
                    $this->settlements->flagExcess($owner, $order->id, $request->attemptId, $at);
                }

                return;
            }

            $monthly = BillingProduct::from($order->product_code)->isMonthly();

            if ($paid && $monthly && ! $attempt->merchant->testMode && ! $result->capturedAt instanceof CarbonImmutable) {
                throw new UnexpectedValueException('Магазин не подтвердил время оплаты месячного периода.');
            }

            $starts = $result->capturedAt ?? $at;
            $anchor = $starts;

            if ($monthly && $order->renewal_due_at !== null && $account->calendar_anchor_at !== null) {
                $due = CarbonImmutable::parse($order->renewal_due_at, 'UTC');

                if ($starts->lessThanOrEqualTo($due) || ($order->purpose === 'renewal' && $starts->setTimezone('Europe/Moscow')->isSameDay($due->setTimezone('Europe/Moscow')))) {
                    $starts = $due;
                    $anchor = CarbonImmutable::parse($account->calendar_anchor_at, 'UTC');
                }
            }

            $next = $starts->setTimezone('Europe/Moscow')->addMonthNoOverflow();
            $ends = $paid && $monthly ? $next->day(min($anchor->setTimezone('Europe/Moscow')->day, $next->daysInMonth))->utc() : null;
            $retry = $status === PaymentStatus::CANCELLED && $order->purpose === 'renewal' && in_array($result->cancellationReason, ['insufficient_funds', 'issuer_unavailable'], true)
                && $this->settlements->attemptCount($order->id) === 1;
            $this->settlements->finishOrder($order->id, $paid, $retry, $starts, $ends, $at);

            if (! $paid) {
                if (! $retry && $order->consent_revision !== null && $order->consent_revision === $account->consent_revision) {
                    $this->settlements->disableRenewalAfterCancellation($owner, $at);
                }

                return;
            }

            $this->receipts->queue($request->attemptId);

            if ($this->settlements->hasEntitlement($owner, $order->entitlement_key)) {
                $this->settlements->flagExcess($owner, $order->id, $request->attemptId, $at);

                return;
            }

            $this->settlements->grant($owner, $order->id, $order->entitlement_key, $order->seconds, $starts, $ends);

            if ($monthly && $order->purpose !== 'renewal' && $order->consent_revision !== null && $order->consent_revision === $account->consent_revision && $account->renewal_consent) {
                $this->settlements->savePaymentMethod($owner, $attempt->merchant, $result);
            }

            if ($monthly && $order->purpose !== 'renewal' && $order->consent_revision === null) {
                $this->settlements->disableRenewal($owner);
            }

            if ($monthly) {
                $this->settlements->startSubscription($owner, $order->product_code, $anchor, $ends, $at);
            }
        }, 3);
    }
}
