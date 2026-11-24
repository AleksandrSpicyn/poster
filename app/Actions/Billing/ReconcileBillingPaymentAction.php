<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PaymentGatewayFactory;
use App\Data\Billing\CheckoutAttemptData;
use App\Enums\PaymentStatus;
use App\Repositories\BillingCheckoutRepository;

final readonly class ReconcileBillingPaymentAction
{
    public function __construct(private BillingCheckoutRepository $checkout, private PaymentGatewayFactory $gateways, private RecordBillingPaymentAction $record) {}

    public function run(string $attemptId, string $externalId, ?string $provider = null, bool $restore = false): ?PaymentStatus
    {
        $attempt = $restore ? $this->checkout->settledAttempt($attemptId, $externalId) : $this->checkout->sentAttempt($attemptId, $externalId, $provider);

        if (! $attempt instanceof CheckoutAttemptData) {
            return null;
        }

        $payment = $this->gateways->payments($attempt->merchant)->findPayment($externalId);
        $this->record->run($attempt, $payment, $restore);

        return $payment->status;
    }
}
