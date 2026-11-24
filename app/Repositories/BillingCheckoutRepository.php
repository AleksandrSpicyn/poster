<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Billing\FiscalProfile;
use App\Data\Billing\CheckoutAttemptData;
use App\Data\Billing\CheckoutData;
use App\Data\Billing\MerchantData;
use App\Data\Billing\PaymentRequestData;
use App\Enums\BillingProduct;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** @phpstan-type CheckoutRow object{order_id: int, provider: string, shop: string, idempotency_key: string, amount_kopecks: int, checkout_return_url: string, external_id: ?string, sent_at: ?string, test_mode: bool} */
final readonly class BillingCheckoutRepository
{
    public function __construct(private BillingWorkspaceRepository $workspace) {}

    /** @return object{sales_enabled: bool, automatic_payments_enabled: bool, consent_revision: int, renews_at: ?string}|null */
    public function lockCheckoutAccount(int $owner): ?object
    {
        /** @var object{sales_enabled: bool, automatic_payments_enabled: bool, consent_revision: int, renews_at: ?string}|null */
        return DB::table('billing_accounts')->where('owner_id', $owner)->lockForUpdate()->first();
    }

    /** @return object{id: int, product_code: string, consent_revision: ?int}|null */
    public function orderForRequest(int $owner, string $requestKey): ?object
    {
        /** @var object{id: int, product_code: string, consent_revision: ?int}|null */
        return DB::table('billing_orders')->where('owner_id', $owner)->where('request_key', $requestKey)->first([
            'id',
            'product_code',
            'consent_revision',
        ]);
    }

    public function enableRenewalConsent(int $owner, BillingProduct $product, string $catalogVersion, int $revision, CarbonImmutable $now): void
    {
        DB::table('billing_accounts')->where('owner_id', $owner)->update([
            'consent_revision' => $revision,
            'renewal_consent' => true,
            'payment_method_id' => null,
            'payment_method_last_four' => null,
            'updated_at' => $now,
        ]);
        DB::table('billing_consents')->insert([
            'owner_id' => $owner,
            'actor_id' => $owner,
            'revision' => $revision,
            'action' => 'enable',
            'product_code' => $product->value,
            'amount_kopecks' => $product->amountKopecks(),
            'terms_version' => $catalogVersion,
            'created_at' => $now,
        ]);
    }

    public function createCheckoutOrder(int $owner, BillingProduct $product, CheckoutData $data, ?int $revision, ?string $due, CarbonImmutable $now): int
    {
        return DB::table('billing_orders')->insertGetId([
            'owner_id' => $owner,
            'product_code' => $product->value,
            'purpose' => $product->isMonthly() ? 'monthly' : 'one_off',
            'offer_url' => $data->accept_offer ? FiscalProfile::offerUrl() : null,
            'purchase_key' => $data->request_key,
            'entitlement_key' => $due !== null ? 'month:' . $due : ($product->isMonthly() ? 'month:first' : 'one_off'),
            'consent_revision' => $revision,
            'renewal_due_at' => $due,
            'fiscal_profile' => ($profile = FiscalProfile::configured()) === null ? null : json_encode($profile, JSON_THROW_ON_ERROR),
            'receipt_email' => DB::table('users')->where('id', $owner)->value('email'),
            'request_key' => $data->request_key,
            'catalog_version' => $data->catalog_version,
            'amount_kopecks' => $product->amountKopecks(),
            'seconds' => $product->seconds(),
            'currency' => 'RUB',
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function scheduleCheckout(string $attempt, string $returnUrl, CarbonImmutable $now): void
    {
        DB::table('billing_payments')->where('id', $attempt)->update([
            'checkout_return_url' => $returnUrl,
            'next_check_at' => $now,
        ]);
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
                $attempt->update([
                    'status' => PaymentStatus::CANCELLED->value,
                    'reason' => 'sales_closed',
                    'next_check_at' => null,
                    'updated_at' => $at,
                ]);
                DB::table('billing_orders')->where('id', $row->order_id)->update(['status' => 'cancelled', 'updated_at' => $at]);
                DB::table('billing_events')->where('event_key', 'payment.prepared.' . $id)->update([
                    'status' => 'processed',
                    'next_check_at' => null,
                    'updated_at' => $at,
                ]);

                return null;
            }

            // A lost creation response must be investigated before sending another charge.
            if ($row->external_id === null && $row->sent_at !== null) {
                $attempt->update(['next_check_at' => null]);

                return null;
            }

            $attempt->update([
                'sent_at' => $row->sent_at ?? $at,
                'next_check_at' => $at->copy()->addMinutes(2),
                'status' => $row->external_id === null ? PaymentStatus::UNKNOWN->value : PaymentStatus::PENDING->value,
                'updated_at' => $at,
            ]);

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

    /** @return object{external_id: ?string, confirmation_url: ?string}|null */
    public function lockOpenAttempt(string $id): ?object
    {
        /** @var object{external_id: ?string, confirmation_url: ?string}|null */
        return $this->openAttempts()->where('id', $id)->lockForUpdate()->first();
    }
}
