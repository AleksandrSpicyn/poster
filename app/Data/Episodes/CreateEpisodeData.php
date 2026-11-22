<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class CreateEpisodeData extends Data
{
    public function __construct(#[Min(1), Max(255)] public string $title, #[Max(2000)] public ?string $objective = null) {}
}
