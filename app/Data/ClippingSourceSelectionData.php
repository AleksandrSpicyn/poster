<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class ClippingSourceSelectionData extends Data
{
    public function __construct(#[Min(1)] public ?int $preview = null, #[Max(200)] public ?string $search = null) {}
}
