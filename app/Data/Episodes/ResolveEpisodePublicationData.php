<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ResolveEpisodePublicationData extends Data
{
    public function __construct(
        #[Required, Max(2048)]
        public string $external_id,
    ) {}
}
