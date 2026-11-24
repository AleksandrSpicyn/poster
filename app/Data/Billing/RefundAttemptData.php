<?php

declare(strict_types=1);

namespace App\Data\Billing;

final readonly class RefundAttemptData
{
    public function __construct(public string $id, public MerchantData $merchant, public RefundRequestData $request, public ?string $externalId, public bool $recovering) {}
}
