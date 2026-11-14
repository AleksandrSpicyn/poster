<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class GetPostsListData extends Data
{
    public function __construct(
        public ?string $orderBy = 'created_at',
        public ?string $sort = 'desc',
        public ?int $perPage = 10,
        public ?int $page = 1,
        public ?string $type = null,
    ) {}
}
