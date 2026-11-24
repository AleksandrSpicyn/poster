<?php

declare(strict_types=1);

namespace App\Data\Production;

use App\Classes\Channels\ChannelIdRule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class StartProductionData extends Data
{
    /** @param list<int>|null $channel_ids */
    public function __construct(
        #[Required, Min(1), Max(100)] public string $request_key,
        #[Min(1)] public ?int $series_id = null,
        #[Max(20)] public ?array $channel_ids = null,
    ) {}

    /** @return array<string, ChannelIdRule> */
    public static function rules(): array
    {
        return ['channel_ids.*' => new ChannelIdRule()];
    }
}
