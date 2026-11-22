<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ClippingConnectionData extends Data
{
    public function __construct(
        #[Required, Max(255)] public string $name,
        #[Required, Max(32)] public string $driver,
        #[Required, Max(4096)] public string $api_key,
    ) {}
}
