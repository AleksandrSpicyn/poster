<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class PublicationTimelineData extends Data
{
    /**
     * @param array<string, int|float> $snapshots
     * @param list<array{from: string, to: string, change: int|float}> $intervals
     */
    public function __construct(
        public string $platform,
        public string $account,
        public string $mediaId,
        public string $title,
        public ?string $url = null,
        public array $snapshots = [],
        public array $intervals = [],
        public int|float|null $change = null,
    ) {}
}
