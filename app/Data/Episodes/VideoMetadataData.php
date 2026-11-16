<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Data;

class VideoMetadataData extends Data
{
    public function __construct(public readonly int $durationMs, public readonly bool $hasAudio) {}
}
