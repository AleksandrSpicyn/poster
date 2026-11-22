<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ClippingAccountSelectionData extends Data
{
    public function __construct(
        #[Required, Max(255)] public string $id,
        #[Min(1)] public ?int $channel_id = null,
    ) {}
}
