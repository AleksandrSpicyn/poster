<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class GetPromptsListData extends Data
{
    public function __construct(
        public ?string $orderBy = 'created_at',
        public ?string $sort = 'desc',
        public ?int $perPage = 10,
        public ?int $page = 1,
    ) {
    }
}
