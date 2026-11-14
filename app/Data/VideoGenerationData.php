<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class VideoGenerationData extends Data
{
    public function __construct(public string $id, public bool $alternative) {}
}
