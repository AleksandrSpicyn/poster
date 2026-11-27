<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class AudioReferenceData extends Data
{
    public function __construct(public readonly string $url, public readonly int $durationMs) {}
}
