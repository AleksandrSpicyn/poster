<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class AcceptEpisodeData extends Data
{
    public function __construct(
        #[Min(0)] public int $lock_version,
        #[Min(1), Max(12000)] public string $confirmed_events,
        #[Min(1), Max(8000)] public string $character_state,
        #[Max(8000)] public string $open_threads,
        public bool $scene_closed,
    ) {}
}
