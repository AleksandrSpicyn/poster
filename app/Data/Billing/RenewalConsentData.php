<?php

declare(strict_types=1);

namespace App\Data\Billing;

use App\Enums\BillingProduct;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;

final class RenewalConsentData extends Data
{
    public function __construct(public bool $enabled, #[In(BillingProduct::CATALOG_VERSION)] public string $catalog_version, public BillingProduct $product) {}
}
