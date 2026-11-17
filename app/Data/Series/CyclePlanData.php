<?php

declare(strict_types=1);

namespace App\Data\Series;

use App\Enums\CycleStructure;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class CyclePlanData extends Data
{
    /** @param list<CyclePartData> $parts */
    public function __construct(
        #[Min(1), Max(5000)] public string $objective,
        public CycleStructure $structure,
        #[DataCollectionOf(CyclePartData::class), Min(1), Max(20)] public array $parts,
    ) {}
}
