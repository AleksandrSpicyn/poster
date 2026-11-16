<?php

declare(strict_types=1);

namespace App\Data\Projects;

use Spatie\LaravelData\Data;

class PublishingSourceData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
