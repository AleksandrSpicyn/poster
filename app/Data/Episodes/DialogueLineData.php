<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class DialogueLineData extends Data
{
    public function __construct(
        #[Min(1)] public int $character_id,
        #[Max(2000)] public string $text,
        #[Min(0)] public ?int $start_ms = null,
        #[Min(1)] public ?int $end_ms = null,
    ) {}
}
