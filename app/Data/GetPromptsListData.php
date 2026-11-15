<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class GetPromptsListData extends Data
{
    public function __construct(
        #[In('asc', 'desc')]
        public string|null|Optional $sort = new Optional(),
        #[IntegerType, Min(1), Max(100)]
        public ?int $perPage = 10,
        #[IntegerType, Min(1)]
        public ?int $page = 1,
    ) {}
}
