<?php

declare(strict_types=1);

namespace App\Data\Series;

use Spatie\LaravelData\Attributes\Validation\Accepted;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class AcceptSeriesVideoData extends Data
{
    public function __construct(
        #[Min(0)] public int $lock_version,
        #[Accepted] public bool $boundaries_reviewed,
        #[Min(1), Max(2000)] public string $review_notes,
    ) {}
}
