<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use App\Support\DistinctModelIdRule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PublishEpisodeData extends Data
{
    /** @param list<int> $channel_ids */
    public function __construct(
        #[Min(1), Max(20)] public array $channel_ids,
        #[Max(1000)] public string|Optional|null $caption = new Optional(),
    ) {}

    /** @return array<string, DistinctModelIdRule> */
    public static function rules(): array
    {
        return ['channel_ids.*' => new DistinctModelIdRule()];
    }
}
