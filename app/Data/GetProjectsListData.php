<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\SortDirection;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class GetProjectsListData extends Data
{
    public function __construct(
        #[Max(200)]
        public ?string $search = null,
        #[Enum(SortDirection::class)]
        public ?SortDirection $sort = null,
        #[IntegerType, Min(1), Max(100)]
        public ?int $perPage = 10,
        #[IntegerType, Min(1)]
        public ?int $page = 1,
        public bool $active = false,
        public bool $updated = false,
        public bool $alphabetical = false,
        #[IntegerType, Min(1)] public ?int $info = null,
    ) {}
}
