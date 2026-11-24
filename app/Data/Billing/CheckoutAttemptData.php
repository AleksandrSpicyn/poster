<?php

declare(strict_types=1);

namespace App\Data\Billing;

final readonly class CheckoutAttemptData
{
    public function __construct(public MerchantData $merchant, public PaymentRequestData $request, public ?string $externalId) {}
}
