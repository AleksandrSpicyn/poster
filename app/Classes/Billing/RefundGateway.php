<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use App\Data\Billing\RefundRequestData;
use App\Data\Billing\RefundResultData;

interface RefundGateway
{
    public function createRefund(RefundRequestData $request): RefundResultData;

    public function findRefund(string $externalId): RefundResultData;
}
