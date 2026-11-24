<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Billing\CheckoutAvailability;
use App\Classes\Billing\FiscalProfile;
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

/** @phpstan-type CheckoutRow object{order_id: int, provider: string, shop: string, idempotency_key: string, amount_kopecks: int, checkout_return_url: string, external_id: ?string, sent_at: ?string, test_mode: bool} */
final readonly class BillingCheckoutRepository
{
    public function __construct(private BillingReceiptRepository $receipts, private BillingWorkspaceRepository $workspace, private BillingPaymentRepository $payments, private PaymentGatewayFactory $gateways, private CheckoutAvailability $availability) {}

    public function open(int $owner, BillingProduct $product, CheckoutData $data): int
    {
        return DB::transaction(function () use ($owner, $product, $data): int {
            /** @var object{sales_enabled: bool, automatic_payments_enabled: bool, renewal_consent: bool, consent_revision: int, renews_at: ?string, calendar_anchor_at: ?string, payment_method_id: ?string, payment_method_provider: ?string, payment_method_shop: ?string, payment_method_test_mode: ?bool, current_product: ?string, next_product: ?string}|null $account */
            $account = DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->first();
            /** @var object{id: int, product_code: string, consent_revision: ?int}|null $previous */
            $previous = DB::table('billing_orders')->where('owner_id', $owner)->where('request_key', $data->request_key)->first(['id', 'product_code', 'consent_revision']);

            if ($previous !== null) {
                if ($previous->product_code !== $product->value || ($previous->consent_revision !== null) !== $data->auto_renew) {
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

            $merchant = $this->gateways->defaultMerchant();

            if (! $merchant->testMode && ! $data->accept_offer) {
                throw ValidationException::withMessages(['accept_offer' => 'Для покупки подтвердите условия оферты.']);
            }

            $now = now('UTC');
            $revision = null;

            if ($data->auto_renew) {
                if (! $product->isMonthly() || $account === null || ! $account->automatic_payments_enabled) {
                    throw ValidationException::withMessages(['auto_renew' => 'Автопродление для этого заказа недоступно.']);
                }

                $revision = $account->consent_revision + 1;
                DB::table('billing_accounts')->where('owner_id', $owner)->update(['consent_revision' => $revision, 'renewal_consent' => true,
                    'payment_method_id' => null, 'payment_method_last_four' => null, 'updated_at' => $now]);
                DB::table('billing_consents')->insert(['owner_id' => $owner, 'actor_id' => $owner, 'revision' => $revision,
                    'action' => 'enable', 'product_code' => $product->value, 'amount_kopecks' => $product->amountKopecks(), 'terms_version' => $data->catalog_version, 'created_at' => $now]);
            }

            $due = $product->isMonthly() ? ($account->renews_at ?? null) : null;
            $order = DB::table('billing_orders')->insertGetId([
                'owner_id' => $owner, 'product_code' => $product->value, 'purpose' => $product->isMonthly() ? 'monthly' : 'one_off',
                'offer_url' => $data->accept_offer ? FiscalProfile::offerUrl() : null, 'purchase_key' => $data->request_key, 'entitlement_key' => $due !== null ? 'month:' . $due : ($product->isMonthly() ? 'month:first' : 'one_off'),
                'consent_revision' => $revision, 'renewal_due_at' => $due, 'fiscal_profile' => ($profile = FiscalProfile::configured()) === null ? null : json_encode($profile, JSON_THROW_ON_ERROR), 'receipt_email' => DB::table('users')->where('id', $owner)->value('email'),
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
        return DB::table('billing_payments')->whereNotNull('open_order_id')->whereNotNull('checkout_return_url');
    }

    public function attemptForOrder(int $order): ?string
    {
        /** @var string|null */
        return $this->openAttempts()->where('order_id', $order)->value('id');
    }

    public function claim(string $id): ?CheckoutAttemptData
    {
        return DB::transaction(function () use ($id): ?CheckoutAttemptData {
            /** @var int $owner */
            $owner = DB::table('billing_orders as o')->join('billing_payments as p', 'p.order_id', '=', 'o.id')->where('p.id', $id)->value('o.owner_id');
            /** @var object{sales_enabled: bool, automatic_payments_enabled: bool, renewal_consent: bool, consent_revision: int, renews_at: ?string, calendar_anchor_at: ?string, payment_method_id: ?string, payment_method_provider: ?string, payment_method_shop: ?string, payment_method_test_mode: ?bool, current_product: ?string, next_product: ?string}|null $account */
            $account = DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->first();
            /** @var CheckoutRow|null $row */
            $row = $this->openAttempts()->where('id', $id)->where('next_check_at', '<=', now('UTC'))->lockForUpdate()->first();

            if ($row === null) {
                return null;
            }

            $at = now('UTC');
            $attempt = DB::table('billing_payments')->where('id', $id);

            /** @var object{purpose: string, product_code: string, catalog_version: string, consent_revision: ?int, renewal_due_at: ?string} $order */
            $order = DB::table('billing_orders')->where('id', $row->order_id)->firstOrFail();
            $automatic = $order->purpose === 'renewal';
            $consented = $account !== null && $account->automatic_payments_enabled && $account->renewal_consent
                && $order->consent_revision === $account->consent_revision;
            $canCharge = $automatic && $consented && $this->workspace->hasMandate($owner, $account->consent_revision, BillingProduct::from($order->product_code)) && $account->payment_method_id !== null && $account->payment_method_provider === $row->provider
                && $account->payment_method_shop === $row->shop && (bool) $account->payment_method_test_mode === (bool) $row->test_mode
                && $account->renews_at === $order->renewal_due_at && ($account->next_product ?? $account->current_product) === $order->product_code
                && $order->catalog_version === BillingProduct::CATALOG_VERSION && BillingProduct::from($order->product_code)->amountKopecks() === $row->amount_kopecks;

            if ($row->sent_at === null && $row->external_id === null && (! ($account->sales_enabled ?? false) || (! $row->test_mode && ! FiscalProfile::liveReady()) || ($automatic && ! $canCharge))) {
                $attempt->update(['status' => PaymentStatus::CANCELLED->value, 'reason' => 'sales_closed', 'next_check_at' => null, 'updated_at' => $at]);
                DB::table('billing_orders')->where('id', $row->order_id)->update(['status' => 'cancelled', 'updated_at' => $at]);
                DB::table('billing_events')->where('event_key', 'payment.prepared.' . $id)->update(['status' => 'processed', 'next_check_at' => null, 'updated_at' => $at]);

                return null;
            }

            // A lost creation response must be investigated before sending another charge.
            if ($row->external_id === null && $row->sent_at !== null) {
                $attempt->update(['next_check_at' => null]);

                return null;
            }

            $attempt->update(['sent_at' => $row->sent_at ?? $at, 'next_check_at' => $at->copy()->addMinutes(2),
                'status' => $row->external_id === null ? PaymentStatus::UNKNOWN->value : PaymentStatus::PENDING->value, 'updated_at' => $at]);

            return $this->attempt($id, $row, $row->external_id, $consented && ! $automatic, $automatic && $canCharge ? $account->payment_method_id : null);
        }, 3);
    }

    public function sentAttempt(string $id, string $externalId, ?string $provider): ?CheckoutAttemptData
    {
        /** @var CheckoutRow|null $row */
        $row = $this->openAttempts()->where('id', $id)->whereNotNull('sent_at')->where('currency', 'RUB')
            ->when($provider !== null, fn(Builder $query) => $query->where('provider', $provider))
            ->where(fn(Builder $query) => $query->whereNull('external_id')->orWhere('external_id', $externalId))->first();

        return $row === null ? null : $this->attempt($id, $row, $externalId);
    }

    public function settledAttempt(string $id, string $external): ?CheckoutAttemptData
    {
        /** @var CheckoutRow|null $row */
        $row = DB::table('billing_payments')->where('id', $id)->where('status', 'succeeded')->where('currency', 'RUB')
            ->whereNotNull('checkout_return_url')->where('external_id', $external)->first();

        return $row === null ? null : $this->attempt($id, $row, $external);
    }

    /** @param CheckoutRow $row */
    private function attempt(string $id, object $row, ?string $externalId, bool $saveMethod = false, ?string $method = null): CheckoutAttemptData
    {
        return new CheckoutAttemptData(
            new MerchantData($row->provider, $row->shop, (bool) $row->test_mode),
            new PaymentRequestData($row->order_id, $id, $row->idempotency_key, $row->amount_kopecks, 'Poster · заказ №' . $row->order_id, $row->checkout_return_url, $saveMethod, $method),
            $externalId,
        );
    }

    public function record(CheckoutAttemptData $attempt, PaymentResultData $result, bool $restore = false): void
    {
        $request = $attempt->request;

        if ($result->attemptId !== $request->attemptId || $result->orderId !== (string) $request->orderId || $result->amountKopecks !== $request->amountKopecks
            || ($attempt->externalId !== null && $result->externalId !== $attempt->externalId)) {
            throw new UnexpectedValueException('Ответ провайдера расходится с заказом.');
        }

        DB::transaction(function () use ($request, $result, $attempt, $restore): void {
            /** @var int $owner */
            $owner = DB::table('billing_orders')->where('id', $request->orderId)->value('owner_id');
            /** @var object{calendar_anchor_at: ?string, consent_revision: int, renewal_consent: bool} $account */
            $account = DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->firstOrFail();
            /** @var object{id: int, product_code: string, seconds: int, entitlement_key: string, purpose: string, consent_revision: ?int, renewal_due_at: ?string, status: string, period_starts_at: ?string, period_ends_at: ?string} $order */
            $order = DB::table('billing_orders')->where('id', $request->orderId)->lockForUpdate()->firstOrFail();
            $active = $this->openAttempts()->where('id', $request->attemptId)->lockForUpdate()->first();

            if ($active === null) {
                if ($restore && $result->status === PaymentStatus::SUCCEEDED && $order->status === 'paid' && $order->period_starts_at !== null
                    && ! DB::table('generation_grants')->where('owner_id', $owner)->where('entitlement_key', $order->entitlement_key)->exists()
                    && ! DB::table('billing_refunds')->where('payment_id', $request->attemptId)->where('status', '!=', 'cancelled')->exists()) {
                    $this->grant(
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
            DB::table('billing_payments')->where('id', $request->attemptId)->update([
                'reason' => $result->cancellationReason, 'status' => $status->value, 'external_id' => $result->externalId,
                'confirmation_url' => $finished ? null : ($result->confirmationUrl ?? $active->confirmation_url),
                'confirmed_at' => $finished ? $at : null, 'next_check_at' => $finished ? null : $at->addMinute(), 'updated_at' => $at,
            ]);
            DB::table('billing_events')->where('event_key', 'payment.prepared.' . $request->attemptId)
                ->update(['status' => 'processed', 'next_check_at' => null, 'updated_at' => $at]);

            if (! $finished) {
                return;
            }

            if (in_array($order->status, ['paid', 'partially_refunded', 'refunded'], true)) {
                if ($paid) {
                    $this->receipts->queue($request->attemptId);
                    $this->flagExcess($owner, $order->id, $request->attemptId, $at);
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
                && DB::table('billing_payments')->where('order_id', $order->id)->count() === 1;
            DB::table('billing_orders')->where('id', $order->id)->update([
                'retry_at' => $retry ? $at->addDays(2) : null, 'status' => $paid ? 'paid' : 'cancelled', 'period_starts_at' => $paid ? $starts : null, 'period_ends_at' => $ends, 'updated_at' => $at,
            ]);

            if (! $paid) {
                if (! $retry && $order->consent_revision !== null && $order->consent_revision === $account->consent_revision) {
                    DB::table('billing_accounts')->where('owner_id', $owner)->update(['renewal_consent' => false, 'updated_at' => $at]);
                }

                return;
            }

            $this->receipts->queue($request->attemptId);

            if (DB::table('generation_grants')->where('owner_id', $owner)->where('entitlement_key', $order->entitlement_key)->exists()) {
                $this->flagExcess($owner, $order->id, $request->attemptId, $at);

                return;
            }

            $this->grant($owner, $order->id, $order->entitlement_key, $order->seconds, $starts, $ends);

            if ($monthly && $order->purpose !== 'renewal' && $order->consent_revision !== null && $order->consent_revision === $account->consent_revision && $account->renewal_consent) {
                DB::table('billing_accounts')->where('owner_id', $owner)->update(['renewal_consent' => $result->savedMethodId !== null,
                    'payment_method_id' => $result->savedMethodId, 'payment_method_last_four' => $result->lastFour,
                    'payment_method_provider' => $attempt->merchant->provider, 'payment_method_shop' => $attempt->merchant->shop, 'payment_method_test_mode' => $attempt->merchant->testMode]);
            }

            if ($monthly && $order->purpose !== 'renewal' && $order->consent_revision === null) {
                DB::table('billing_accounts')->where('owner_id', $owner)->update(['renewal_consent' => false]);
            }

            if ($monthly) {
                DB::table('billing_accounts')->where('owner_id', $owner)->update(['current_product' => $order->product_code, 'next_product' => null, 'calendar_anchor_at' => $anchor, 'renews_at' => $ends, 'updated_at' => $at]);
            }
        }, 3);
    }

    private function flagExcess(int $owner, int $order, string $attempt, CarbonImmutable $at): void
    {
        DB::table('billing_events')->insert(['owner_id' => $owner, 'event_key' => 'payment.excess.' . $attempt, 'kind' => 'payment.excess', 'status' => 'needs_review',
            'payload' => json_encode(['order' => $order, 'attempt' => $attempt], JSON_THROW_ON_ERROR), 'created_at' => $at, 'updated_at' => $at]);
    }

    private function grant(int $owner, int $order, string $key, int $seconds, CarbonImmutable $starts, ?CarbonImmutable $ends): void
    {
        $at = CarbonImmutable::now('UTC');
        $grant = DB::table('generation_grants')->insertGetId([
            'order_id' => $order, 'owner_id' => $owner, 'entitlement_key' => $key, 'starts_at' => $starts, 'ends_at' => $ends,
            'granted_seconds' => $seconds, 'available_seconds' => $seconds, 'created_at' => $at, 'updated_at' => $at,
        ]);
        DB::table('generation_credit_entries')->insert(['owner_id' => $owner, 'grant_id' => $grant, 'kind' => 'grant', 'event_key' => 'checkout.granted.' . $order,
            'granted_delta' => $seconds, 'available_delta' => $seconds, 'created_at' => $at]);
    }
}
