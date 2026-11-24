<?php

declare(strict_types=1);

namespace App\Data\Billing;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

final class GenerationQuoteData extends Data
{
    public function __construct(#[Min(1)] public int $project_id, #[Min(1)] public ?int $series_id = null, #[Min(1)] public ?int $revision_id = null, #[Min(1)] public ?int $shot_id = null) {}
}
