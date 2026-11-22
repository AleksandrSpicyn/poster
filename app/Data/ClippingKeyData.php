<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ClippingKeyData extends Data
{
    public function __construct(#[Required, Max(4096)] public string $api_key) {}
}
