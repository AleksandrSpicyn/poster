<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class PublicationMetricsData extends Data
{
    /**
     * @param array<string, int|float|null> $daily
     * @param list<array{name: string, status: string, last_success_at: string|null}> $coverage
     * @param list<array{id: int, name: string}> $projects
     * @param list<PublicationTimelineData> $publications
     */
    public function __construct(
        public MetricPeriodData $filters,
        public string $from,
        public string $to,
        public ?string $updatedAt,
        public int|float|null $change,
        public array $daily,
        public array $publications,
        public array $coverage,
        public array $projects,
    ) {}
}
