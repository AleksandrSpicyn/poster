<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class PostData extends Data
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $url = null,
        public ?string $externalId = null,
    ) {
    }
}
