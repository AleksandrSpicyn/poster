<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ChannelType;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class GetPostsListData extends Data
{
    public function __construct(
        #[In('asc', 'desc')]
        public string|null|Optional $sort = new Optional(),
        #[IntegerType, Min(1), Max(100)]
        public ?int $perPage = 10,
        #[IntegerType, Min(1)]
        public ?int $page = 1,
        #[In('All', ChannelType::YOUTUBE, ChannelType::TIKTOK, ChannelType::INSTAGRAM, ChannelType::VK)]
        public string|null|Optional $type = new Optional(),
    ) {}
}
