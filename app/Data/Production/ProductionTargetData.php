<?php

declare(strict_types=1);

namespace App\Data\Production;

use App\Models\Series;
use App\Models\User;
use Spatie\LaravelData\Data;

class ProductionTargetData extends Data
{
    public function __construct(
        public ?Series $series,
        public User $actor,
    ) {}
}
