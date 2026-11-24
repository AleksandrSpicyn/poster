<?php

declare(strict_types=1);

namespace App\Data\Billing;

use App\Enums\BillingSection;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

final class BillingHistoryData extends Data
{
    public function __construct(
        #[In(BillingSection::PAYMENTS->value, BillingSection::SECONDS->value)] public BillingSection $tab = BillingSection::PAYMENTS,
        #[Min(1)] public int $page = 1,
        #[Min(1)] public ?int $project = null,
    ) {}
}
