<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PaymentGatewayFactory;
use App\Data\Billing\ReceiptAttemptData;
use App\Repositories\BillingReceiptRepository;

final readonly class SyncBillingReceiptAction
{
    public function __construct(private BillingReceiptRepository $receipts, private PaymentGatewayFactory $gateways) {}

    public function run(int $id, bool $retry = false, ?string $external = null): void
    {
        $attempt = $this->receipts->claim($id, $retry, $external);

        if (! $attempt instanceof ReceiptAttemptData) {
            return;
        }

        $gateway = $this->gateways->receipts($attempt->merchant);
        $result = $attempt->external === null ? $gateway->createReceipt($attempt->request, $attempt->key) : $gateway->findReceipt($attempt->request, $attempt->external);
        $this->receipts->record($attempt, $result);
    }
}
