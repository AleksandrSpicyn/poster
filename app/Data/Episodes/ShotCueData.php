<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use App\Enums\ShotCueKind;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class ShotCueData extends Data
{
    public function __construct(
        public ShotCueKind $kind,
        #[Min(0)] public int $start_ms,
        #[Min(1)] public int $end_ms,
        #[Min(1), Max(500)] public string $text,
        #[Min(1)] public ?int $asset_id = null,
    ) {}
}
