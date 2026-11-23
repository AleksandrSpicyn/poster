<?php

declare(strict_types=1);

namespace App\Data\Billing;

use App\Enums\PaymentStatus;

final readonly class RefundResultData
{
    public function __construct(public string $externalId, public string $paymentId, public PaymentStatus $status, public int $amountKopecks) {}
}
