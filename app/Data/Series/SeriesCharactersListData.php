<?php

declare(strict_types=1);

namespace App\Data\Series;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class SeriesCharactersListData extends Data
{
    public function __construct(
        #[Min(1)] public int $page = 1,
    ) {}
}
