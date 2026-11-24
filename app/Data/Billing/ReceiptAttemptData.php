<?php

declare(strict_types=1);

namespace App\Data\Billing;

final readonly class ReceiptAttemptData
{
    public function __construct(public int $id, public MerchantData $merchant, public ReceiptRequestData $request, public string $key, public ?string $external) {}
}
