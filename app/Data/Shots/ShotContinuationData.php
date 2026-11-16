<?php

declare(strict_types=1);

namespace App\Data\Shots;

use Spatie\LaravelData\Data;

class ShotContinuationData extends Data
{
    public function __construct(
        public readonly ?int $sourceMediaId,
        public readonly ?string $sourceChecksum,
        public readonly ?int $sourcePromptId,
        public readonly ?int $sourceAssemblyId,
    ) {}
}
