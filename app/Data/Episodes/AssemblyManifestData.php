<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class AssemblyManifestData extends Data
{
    /** @param list<AssemblyShotData> $shots */
    public function __construct(
        #[DataCollectionOf(AssemblyShotData::class), Min(1), Max(100)] public array $shots,
    ) {}
}
