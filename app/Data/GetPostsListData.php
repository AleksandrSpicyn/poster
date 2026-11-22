<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ChannelType;
use App\Enums\FilterValue;
use App\Enums\SortDirection;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Валидирует параметры списка; all означает отсутствие фильтра канала.
 * Optional отличает непереданный фильтр от явно заданного значения.
 */
class GetPostsListData extends Data
{
    public function __construct(
        #[Enum(SortDirection::class)]
        public SortDirection|null|Optional $sort = new Optional(),
        #[IntegerType, Min(1), Max(100)]
        public ?int $perPage = 10,
        #[IntegerType, Min(1)]
        public ?int $page = 1,
        #[In(FilterValue::ALL, ChannelType::YOUTUBE, ChannelType::TIKTOK, ChannelType::INSTAGRAM, ChannelType::VK)]
        public string|null|Optional $type = new Optional(),
        #[Max(200)]
        public ?string $search = null,
        #[In([0, 7, 30, 90])]
        public int $days = 30,
    ) {}
}
