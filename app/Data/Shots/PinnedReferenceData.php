<?php

declare(strict_types=1);

namespace App\Data\Shots;

use Spatie\LaravelData\Data;

class PinnedReferenceData extends Data
{
    public function __construct(
        public readonly int $media_id,
        public readonly string $sha256,
    ) {}
}
