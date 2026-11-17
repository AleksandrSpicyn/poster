<?php

declare(strict_types=1);

namespace App\Data\Episodes;

final readonly class TimelineClipData
{
    public function __construct(public string $path, public int $startMs, public int $durationMs, public bool $mute) {}
}
