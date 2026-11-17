<?php

declare(strict_types=1);

namespace App\Data\Series;

use App\Enums\TransitionMode;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class CyclePartData extends Data
{
    public function __construct(
        #[Min(1), Max(20)] public int $number,
        #[Min(1), Max(2000)] public string $objective,
        #[Min(1), Max(2000)] public string $expected_result,
        #[Min(1), Max(100)] public string $scene_key,
        public TransitionMode $transition_mode,
    ) {}
}
