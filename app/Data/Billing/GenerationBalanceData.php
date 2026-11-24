<?php

declare(strict_types=1);

namespace App\Data\Billing;

final readonly class GenerationBalanceData
{
    public function __construct(
        public int $available,
        public int $held,
        public int $consumed,
        public int $expired,
    ) {}
}
