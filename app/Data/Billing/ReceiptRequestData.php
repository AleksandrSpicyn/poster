<?php

declare(strict_types=1);

namespace App\Data\Billing;

use Spatie\LaravelData\Data;

final class ReceiptRequestData extends Data
{
    public function __construct(
        public string $kind,
        public string $sourceId,
        public int $amountKopecks,
        public string $email,
        public string $description,
        public int $vat,
        public string $subject,
        public string $mode,
        public ?int $taxSystem,
    ) {}
}
