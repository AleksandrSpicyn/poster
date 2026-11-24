<?php

declare(strict_types=1);

namespace App\Data\Billing;

use App\Enums\BillingProduct;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

final class CheckoutData extends Data
{
    public function __construct(
        #[Uuid] public string $request_key,
        #[In(BillingProduct::CATALOG_VERSION)] public string $catalog_version,
        public bool $auto_renew = false,
        public bool $accept_offer = false,
    ) {}
}
