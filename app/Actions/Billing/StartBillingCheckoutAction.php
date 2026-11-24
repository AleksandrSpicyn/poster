<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\CheckoutAvailability;
use App\Classes\Billing\PaymentGatewayFactory;
use App\Data\Billing\CheckoutData;
use App\Data\Billing\MerchantData;
use App\Enums\BillingProduct;
use App\Models\User;
use App\Repositories\BillingCheckoutRepository;
use App\Repositories\BillingPaymentRepository;
use App\Repositories\BillingWorkspaceRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StartBillingCheckoutAction
{
    public function __construct(
        private BillingCheckoutRepository $checkout,
        private BillingPaymentRepository $payments,
        private BillingWorkspaceRepository $workspace,
        private PaymentGatewayFactory $gateways,
        private CheckoutAvailability $availability,
        private SyncBillingCheckoutAction $sync,
    ) {}

    public function run(User $owner, BillingProduct $product, CheckoutData $data): int
    {
        $order = DB::transaction(fn(): int => $this->openOrder($owner->id, $product, $data), 3);
        $id = $this->checkout->attemptForOrder($order);

        if ($id !== null) {
            $this->sync->run($id);
        }

        return $order;
    }

    private function openOrder(int $owner, BillingProduct $product, CheckoutData $data): int
    {
        $account = $this->checkout->lockCheckoutAccount($owner);
        $previous = $this->checkout->orderForRequest($owner, $data->request_key);

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

        $now = CarbonImmutable::now('UTC');
        $revision = null;

        if ($data->auto_renew) {
            if (! $product->isMonthly() || $account === null || ! $account->automatic_payments_enabled) {
                throw ValidationException::withMessages(['auto_renew' => 'Автопродление для этого заказа недоступно.']);
            }

            $revision = $account->consent_revision + 1;
            $this->checkout->enableRenewalConsent($owner, $product, $data->catalog_version, $revision, $now);
        }

        $due = $product->isMonthly() ? ($account->renews_at ?? null) : null;
        $order = $this->checkout->createCheckoutOrder($owner, $product, $data, $revision, $due, $now);
        $attempt = $this->payments->prepare($owner, $order, fn(): MerchantData => $merchant);
        $returnUrl = rtrim(config()->string('app.url'), '/') . route('billing.orders.show', ['order' => $order], false);
        $this->checkout->scheduleCheckout($attempt, $returnUrl, $now);

        return $order;
    }
}
