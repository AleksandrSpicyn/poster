<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;

class ClippingImportData extends Data
{
    /** @param list<ClippingAccountSelectionData> $accounts */
    public function __construct(
        #[Regex('/^[\w-]{1,128}$/D')] public string $external_id,
        #[Min(1)] public ?int $project_id = null,
        #[Max(255)] public ?string $game = null,
        #[DataCollectionOf(ClippingAccountSelectionData::class), Max(100)] public array $accounts = [],
    ) {}
}
