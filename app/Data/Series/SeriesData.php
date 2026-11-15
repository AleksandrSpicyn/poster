<?php

declare(strict_types=1);

namespace App\Data\Series;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SeriesData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required]
        public SeriesSpecificationData $specification,
        #[Min(0)]
        public ?int $lock_version = null,
    ) {}
}
