<?php

declare(strict_types=1);

namespace App\Data\Billing;

use App\Enums\PaymentStatus;

final readonly class PaymentResultData
{
    public function __construct(
        public string $externalId,
        public PaymentStatus $status,
        public int $amountKopecks,
        public string $orderId,
        public string $attemptId,
        public ?string $confirmationUrl,
        public ?string $savedMethodId,
        public ?string $lastFour,
    ) {}
}
