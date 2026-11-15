<?php

declare(strict_types=1);

namespace App\Data\Shots;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ResolveShotGenerationData extends Data
{
    public function __construct(
        #[Required, Max(200), Regex('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,199}\z/')]
        public string $external_id,
        #[Max(1000)]
        public string $note = '',
    ) {}
}
