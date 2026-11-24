<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PaymentGatewayFactory;
use App\Data\Billing\RefundAttemptData;
use App\Enums\PaymentStatus;
use App\Repositories\BillingRefundRepository;

final readonly class SyncBillingRefundAction
{
    public function __construct(private BillingRefundRepository $refunds, private PaymentGatewayFactory $gateways) {}

    public function notification(string $external, ?string $description): void
    {
        $id = $this->refunds->notification($external, $description);

        if ($id !== null) {
            $this->run($id, $external);
        }
    }

    public function run(string $id, ?string $external = null): ?PaymentStatus
    {
        $attempt = $this->refunds->claim($id, $external);

        if (! $attempt instanceof RefundAttemptData) {
            return null;
        }

        $gateway = $this->gateways->refunds($attempt->merchant);
        $result = $attempt->externalId === null ? $gateway->createRefund($attempt->request) : $gateway->findRefund($attempt->externalId);
        $this->refunds->record($attempt, $result);

        return $attempt->externalId === null ? PaymentStatus::PENDING : $result->status;
    }
}
