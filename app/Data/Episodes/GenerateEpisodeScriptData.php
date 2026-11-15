<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class GenerateEpisodeScriptData extends Data
{
    public function __construct(
        #[Min(1), Max(5000)] public string $brief,
        #[Min(0)] public int $lock_version,
        #[Uuid] public string $request_key,
    ) {}
}
