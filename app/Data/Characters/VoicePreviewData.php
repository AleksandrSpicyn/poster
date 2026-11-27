<?php

declare(strict_types=1);

namespace App\Data\Characters;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class VoicePreviewData extends Data
{
    public function __construct(
        #[Max(128)] public string $voice_id,
        #[Min(1), Max(200)] public string $text,
        #[Min(0)] public int $lock_version,
    ) {}
}
