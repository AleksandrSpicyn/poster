<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ChannelType;
use App\Enums\PublicationCounter;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;

final class MetricPeriodData extends Data
{
    public function __construct(
        #[In([7, 30, 90])]
        public int $days = 7,
        public PublicationCounter $metric = PublicationCounter::VIEWS,
        public ?ChannelType $platform = null,
    ) {}
}
