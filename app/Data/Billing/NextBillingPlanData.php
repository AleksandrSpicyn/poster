<?php

declare(strict_types=1);

namespace App\Data\Billing;

use App\Enums\BillingProduct;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Data;

final class NextBillingPlanData extends Data
{
    public function __construct(#[Present, In(BillingProduct::AUTHOR->value, BillingProduct::STUDIO->value)] public ?BillingProduct $product) {}
}
