<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use App\Data\Billing\PaymentRequestData;
use App\Data\Billing\PaymentResultData;

interface PaymentGateway
{
    public function createPayment(PaymentRequestData $request): PaymentResultData;

    public function findPayment(string $externalId): PaymentResultData;
}
