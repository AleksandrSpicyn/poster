<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use App\Enums\TransitionMode;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class ShotData extends Data
{
    /** @param list<DialogueLineData> $dialogue */
    public function __construct(
        #[Min(4000), Max(30000)] public int $duration_ms,
        #[Min(1), Max(5000)] public string $action,
        #[Max(2000)] public string $camera,
        public TransitionMode $transition_mode,
        #[DataCollectionOf(DialogueLineData::class), Max(20)] public array $dialogue = [],
        #[Min(1)] public ?int $source_prompt_id = null,
        #[Min(1)] public ?int $source_assembly_id = null,
        #[Min(0)] public int $source_time_ms = 0,
    ) {}
}
