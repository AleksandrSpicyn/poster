<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class AssemblyShotData extends Data
{
    public function __construct(
        #[Min(1)] public int $prompt_id,
        #[Min(0)] public int $trim_start_ms = 0,
        #[Min(1)] public ?int $trim_end_ms = null,
    ) {}
}
