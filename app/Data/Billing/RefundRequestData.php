<?php

declare(strict_types=1);

namespace App\Data\Billing;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RefundRequestData
{
    public function __construct(public string $paymentId, public int $amountKopecks, public string $idempotencyKey)
    {
        if ($paymentId === '' || $amountKopecks < 1 || ! Str::isUuid($idempotencyKey)) {
            throw new InvalidArgumentException('Некорректные параметры возврата.');
        }
    }
}
