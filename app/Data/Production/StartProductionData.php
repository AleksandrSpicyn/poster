<?php

declare(strict_types=1);

namespace App\Data\Production;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class StartProductionData extends Data
{
    public function __construct(
        #[Required, Min(1), Max(100)] public string $request_key,
        #[Min(1)] public ?int $series_id = null,
    ) {}
}
