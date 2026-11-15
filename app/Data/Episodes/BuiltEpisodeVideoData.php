<?php

declare(strict_types=1);

namespace App\Data\Episodes;

readonly class BuiltEpisodeVideoData
{
    public function __construct(public string $path, public int $durationMs, public string $workspace) {}
}
