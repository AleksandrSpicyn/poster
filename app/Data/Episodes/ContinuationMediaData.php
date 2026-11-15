<?php

declare(strict_types=1);

namespace App\Data\Episodes;

readonly class ContinuationMediaData
{
    public function __construct(public string $framePath, public string $tailPath, public int $timeMs) {}
}
