<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class SceneData extends Data
{
    /** @param list<ShotData> $shots */
    public function __construct(
        #[Min(1), Max(255)] public string $title,
        #[Min(1), Max(1000)] public string $location,
        #[Min(1), Max(255)] public string $time,
        #[Min(1), Max(2000)] public string $objective,
        #[Max(4000)] public string $initial_state,
        #[Max(4000)] public string $expected_final_state,
        public bool $is_closed,
        #[DataCollectionOf(ShotData::class), Min(1), Max(30)] public array $shots,
    ) {}
}
