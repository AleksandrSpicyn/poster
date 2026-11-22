<?php

declare(strict_types=1);

namespace App\Data\Channels;

use App\Enums\SortDirection;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

final class GetChannelListData extends Data
{
    public function __construct(
        #[Min(1)] public int $page = 1,
        #[In([10, 20, 25, 50, 100])] public int $perPage = 20,
        #[Max(200)] public ?string $search = null,
        public ?bool $active = null,
        public ?SortDirection $sort = null,
    ) {}
}
