<?php

declare(strict_types=1);

namespace App\Data\Shots;

use Spatie\LaravelData\Attributes\Validation\Accepted;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class AbandonShotGenerationData extends Data
{
    public function __construct(
        #[Required, Max(1000)]
        public string $note,
        #[Required, Accepted]
        public bool $accept_unknown_outcome,
    ) {}
}
