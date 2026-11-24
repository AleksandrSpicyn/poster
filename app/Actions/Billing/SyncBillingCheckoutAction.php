<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PaymentGatewayFactory;
use App\Data\Billing\CheckoutAttemptData;
use App\Repositories\BillingCheckoutRepository;
use Throwable;

final readonly class SyncBillingCheckoutAction
{
    public function __construct(private BillingCheckoutRepository $checkout, private PaymentGatewayFactory $gateways, private RecordBillingPaymentAction $record) {}

    public function run(string $id): void
    {
        $attempt = $this->checkout->claim($id);

        if (! $attempt instanceof CheckoutAttemptData) {
            return;
        }

        try {
            $gateway = $this->gateways->payments($attempt->merchant);
            $result = $attempt->externalId === null ? $gateway->createPayment($attempt->request) : $gateway->findPayment($attempt->externalId);
            $this->record->run($attempt, $result);
        } catch (Throwable $error) {
            report($error);
        }
    }
}
