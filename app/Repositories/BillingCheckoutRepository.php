<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Billing\CheckoutAvailability;
use App\Classes\Billing\PaymentGatewayFactory;
use App\Data\Billing\CheckoutAttemptData;
use App\Data\Billing\CheckoutData;
use App\Data\Billing\MerchantData;
use App\Data\Billing\PaymentRequestData;
use App\Data\Billing\PaymentResultData;
use App\Enums\BillingProduct;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

final readonly class BillingCheckoutRepository
{
    public function __construct(private BillingWorkspaceRepository $workspace, private BillingPaymentRepository $payments, private PaymentGatewayFactory $gateways, private CheckoutAvailability $availability) {}

    public function open(int $owner, BillingProduct $product, CheckoutData $data): int
    {
        return DB::transaction(function () use ($owner, $product, $data): int {
            $account = DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->first();
            /** @var object{id: int, product_code: string}|null $previous */
            $previous = DB::table('billing_orders')->where('owner_id', $owner)->where('request_key', $data->request_key)->first(['id', 'product_code']);

            if ($previous !== null) {
                if ($previous->product_code !== $product->value) {
                    throw ValidationException::withMessages(['checkout' => 'Состав заказа изменился. Откройте тариф ещё раз.']);
                }

                return $previous->id;
            }

            $pending = $this->workspace->pendingOrder($owner);

            if ($pending !== null) {
                return $pending;
            }

            $blocked = $this->availability->blocked($owner, $product, (bool) ($account->sales_enabled ?? false));

            if ($blocked !== null) {
                throw ValidationException::withMessages(['checkout' => $blocked]);
            }

            $now = now('UTC');
            $order = DB::table('billing_orders')->insertGetId([
                'owner_id' => $owner, 'product_code' => $product->value, 'purpose' => $product->isMonthly() ? 'monthly' : 'one_off',
                'purchase_key' => $data->request_key, 'entitlement_key' => $product->isMonthly() ? $data->request_key : 'one_off',
                'request_key' => $data->request_key, 'catalog_version' => $data->catalog_version,
                'amount_kopecks' => $product->amountKopecks(), 'seconds' => $product->seconds(), 'currency' => 'RUB',
                'status' => 'open', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $attempt = $this->payments->prepare($owner, $order, $this->gateways->defaultMerchant(...));
            DB::table('billing_payments')->where('id', $attempt)->update([
                'checkout_return_url' => rtrim(config()->string('app.url'), '/') . route('billing.orders.show', ['order' => $order], false),
                'next_check_at' => $now,
            ]);

            return $order;
        }, 3);
    }

    /** @return list<string> */
    public function due(): array
    {
        /** @var list<string> */
        return $this->openAttempts()->where('next_check_at', '<=', now('UTC'))->orderBy('next_check_at')->limit(50)->pluck('id')->all();
    }

    private function openAttempts(): Builder
    {
        return DB::table('billing_payments')->whereNotNull('open_order_id')->where('test_mode', true)->whereNotNull('checkout_return_url');
    }

    public function attemptForOrder(int $order): ?string
    {
        /** @var string|null */
        return $this->openAttempts()->where('order_id', $order)->value('id');
    }

    public function claim(string $id): ?CheckoutAttemptData
    {
        return DB::transaction(function () use ($id): ?CheckoutAttemptData {
            /** @var object{order_id: int, provider: string, shop: string, idempotency_key: string, amount_kopecks: int, checkout_return_url: string, external_id: ?string, sent_at: ?string}|null $row */
            $row = $this->openAttempts()->where('id', $id)->where('next_check_at', '<=', now('UTC'))->lockForUpdate()->first();

            if ($row === null) {
                return null;
            }

            $at = now('UTC');
            $attempt = DB::table('billing_payments')->where('id', $id);

            // A lost creation response must be investigated before sending another charge.
            if ($row->external_id === null && $row->sent_at !== null) {
                $attempt->update(['next_check_at' => null]);

                return null;
            }

            $attempt->update(['sent_at' => $row->sent_at ?? $at, 'next_check_at' => $at->copy()->addMinutes(2),
                'status' => $row->external_id === null ? PaymentStatus::UNKNOWN->value : PaymentStatus::PENDING->value, 'updated_at' => $at]);

            return new CheckoutAttemptData(
                new MerchantData($row->provider, $row->shop, true),
                new PaymentRequestData($row->order_id, $id, $row->idempotency_key, $row->amount_kopecks, 'Poster · заказ №' . $row->order_id, $row->checkout_return_url),
                $row->external_id,
            );
        }, 3);
    }

    public function record(CheckoutAttemptData $attempt, PaymentResultData $result): void
    {
        $request = $attempt->request;

        if ($result->attemptId !== $request->attemptId || $result->orderId !== (string) $request->orderId || $result->amountKopecks !== $request->amountKopecks
            || ($attempt->externalId !== null && $result->externalId !== $attempt->externalId)) {
            throw new UnexpectedValueException('Ответ провайдера расходится с заказом.');
        }

        DB::transaction(function () use ($request, $result, $attempt): void {
            /** @var int $owner */
            $owner = DB::table('billing_orders')->where('id', $request->orderId)->value('owner_id');
            DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->firstOrFail();
            /** @var object{id: int, product_code: string, seconds: int, entitlement_key: string} $order */
            $order = DB::table('billing_orders')->where('id', $request->orderId)->lockForUpdate()->firstOrFail();
            $active = $this->openAttempts()->where('id', $request->attemptId)->lockForUpdate()->first();

            if ($active === null) {
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
            DB::table('billing_payments')->where('id', $request->attemptId)->update([
                'status' => $status->value, 'external_id' => $result->externalId,
                'confirmation_url' => $finished ? null : $result->confirmationUrl,
                'confirmed_at' => $finished ? $at : null, 'next_check_at' => $finished ? null : $at->addMinute(), 'updated_at' => $at,
            ]);
            DB::table('billing_events')->where('event_key', 'payment.prepared.' . $request->attemptId)
                ->update(['status' => 'processed', 'next_check_at' => null, 'updated_at' => $at]);

            if (! $finished) {
                return;
            }

            $monthly = BillingProduct::from($order->product_code)->isMonthly();
            $starts = $result->capturedAt ?? $at;
            $ends = $paid && $monthly ? $starts->setTimezone('Europe/Moscow')->addMonthNoOverflow()->utc() : null;
            DB::table('billing_orders')->where('id', $order->id)->update([
                'status' => $paid ? 'paid' : 'cancelled', 'period_starts_at' => $paid ? $starts : null, 'period_ends_at' => $ends, 'updated_at' => $at,
            ]);

            if (! $paid) {
                return;
            }

            $grant = DB::table('generation_grants')->insertGetId([
                'order_id' => $order->id, 'owner_id' => $owner, 'entitlement_key' => $order->entitlement_key, 'starts_at' => $starts, 'ends_at' => $ends,
                'granted_seconds' => $order->seconds, 'available_seconds' => $order->seconds, 'created_at' => $at, 'updated_at' => $at,
            ]);
            DB::table('generation_credit_entries')->insert(['owner_id' => $owner, 'grant_id' => $grant, 'kind' => 'grant', 'event_key' => 'checkout.granted.' . $order->id,
                'granted_delta' => $order->seconds, 'available_delta' => $order->seconds, 'created_at' => $at]);

            if ($monthly) {
                DB::table('billing_accounts')->where('owner_id', $owner)->update(['current_product' => $order->product_code, 'next_product' => null, 'calendar_anchor_at' => $starts, 'renews_at' => $ends, 'updated_at' => $at]);
            }
        }, 3);
    }
}
