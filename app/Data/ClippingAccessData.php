<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class ClippingAccessData extends Data
{
    public function __construct(#[Min(1)] public ?int $project_id = null, #[Min(1)] public ?int $source_id = null) {}
}
