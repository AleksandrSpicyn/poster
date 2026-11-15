<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class SaveEpisodeScriptData extends Data
{
    public function __construct(
        public EpisodeScriptData $script,
        #[Min(0)] public int $lock_version,
    ) {}
}
