<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class ChannelObservationReportData extends Data
{
    /**
     * @param array<string, int|float|null> $observations
     */
    public function __construct(
        public array $observations,
        public string $previousFrom,
        public string $previousTo,
        public int|float|null $currentChange,
        public int|float|null $previousChange,
        public bool $previousObserved,
    ) {}
}
