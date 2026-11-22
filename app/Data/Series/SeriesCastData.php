<?php

declare(strict_types=1);

namespace App\Data\Series;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class SeriesCastData extends Data
{
    public function __construct(
        #[Min(1)] public ?int $lead_character_id = null,
        #[Max(2000)] public ?string $voice_description = null,
        public bool $use_series_voice = false,
    ) {}
}
