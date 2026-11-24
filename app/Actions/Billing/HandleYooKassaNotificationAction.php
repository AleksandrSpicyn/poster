<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Data\Billing\YooKassaNotificationData;

final readonly class HandleYooKassaNotificationAction
{
    public function __construct(
        private ReconcileBillingPaymentAction $payments,
        private SyncBillingRefundAction $refunds,
    ) {}

    public function run(YooKassaNotificationData $data): void
    {
        if ($data->attemptId !== null && $data->isPaymentNotification()) {
            $this->payments->run($data->attemptId, $data->externalId, 'yookassa');
        }

        if ($data->event === 'refund.succeeded') {
            $this->refunds->notification($data->externalId, $data->description);
        }
    }
}
