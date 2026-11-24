<?php

declare(strict_types=1);

namespace App\Data\Billing;

use App\Enums\PaymentStatus;

final readonly class ReceiptResultData
{
    public function __construct(public string $externalId, public PaymentStatus $status) {}
}
