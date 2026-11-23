<?php

declare(strict_types=1);

namespace App\Data\Billing;

final readonly class StoredPaymentData
{
    public function __construct(public string $attemptId, public string $orderId, public MerchantData $merchant, public int $amountKopecks, public string $currency, public ?string $externalId) {}
}
