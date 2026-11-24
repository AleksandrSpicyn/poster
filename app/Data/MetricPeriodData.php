<?php

declare(strict_types=1);

namespace App\Data;

use App\Classes\Channels\ChannelIdRule;
use App\Enums\ChannelType;
use App\Enums\ProjectContentMode;
use App\Enums\PublicationCounter;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\RequiredWith;
use Spatie\LaravelData\Data;

final class MetricPeriodData extends Data
{
    /** @param list<int> $exclude_channels */
    public function __construct(
        #[In([
            7,
            28,
            30,
            90,
            180,
            365,
        ])] public int $days = 7,
        public PublicationCounter $metric = PublicationCounter::VIEWS,
        public ?ChannelType $platform = null,
        #[Min(1)] public ?int $project = null,
        #[RequiredWith('to'), DateFormat('Y-m-d')] public ?string $from = null,
        #[RequiredWith('from'), DateFormat('Y-m-d'), AfterOrEqual('from'), BeforeOrEqual('today')] public ?string $to = null,
        public ?ProjectContentMode $format = null,
        public array $exclude_channels = [],
        #[Min(1)] public ?int $channel = null,
    ) {}

    /** @return array<string, ChannelIdRule> */
    public static function rules(): array
    {
        return ['exclude_channels.*' => new ChannelIdRule()];
    }
}
