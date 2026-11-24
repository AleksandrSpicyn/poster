<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use App\Data\Billing\ReceiptRequestData;
use App\Data\Billing\ReceiptResultData;

interface ReceiptGateway
{
    public function createReceipt(ReceiptRequestData $request, string $key): ReceiptResultData;

    public function findReceipt(ReceiptRequestData $request, string $id): ReceiptResultData;
}
