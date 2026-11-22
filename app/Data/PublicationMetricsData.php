<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class PublicationMetricsData extends Data
{
    /**
     * @param array<string, int|float|null> $summaries
     * @param array<string, int|float|null> $daily
     * @param list<array{id:int, name:string, platform:string, status:string, state:string|null, last_success_at:string|null}> $coverage
     * @param list<array{id:int, name:string, platform:string, projects:list<int>}> $channels
     * @param array<string, int|float|null> $totals
     * @param list<array<string,mixed>> $projects
     * @param list<PublicationTimelineData> $publications
     */
    public function __construct(
        public MetricPeriodData $filters,
        public string $from,
        public string $to,
        public ?string $updatedAt,
        public int|float|null $change,
        public array $summaries,
        public array $daily,
        public array $publications,
        public array $coverage,
        public array $projects,
        public array $channels,
        public array $totals,
        public ?DashboardProductionData $production = null,
        public ?ChannelObservationReportData $channelReport = null,
    ) {}

    /** @return array<int, PublicationTimelineData> */
    private function observedPublications(): array
    {
        return array_filter($this->publications, fn(PublicationTimelineData $publication): bool => array_intersect_key($publication->snapshots, $this->daily) !== []);
    }

    public function observedChange(): int|float|null
    {
        $rows = $this->observedPublications();
        $changes = array_map(static fn(PublicationTimelineData $row): int|float|null => $row->change, $rows);

        return $changes === [] || in_array(null, $changes, true) ? null : array_sum($changes);
    }

    public function hasObservations(): bool
    {
        return $this->observedPublications() !== [];
    }

    /** @return array<string, int|float|null> */
    public function observedTotals(): array
    {
        $daily = array_fill_keys(array_keys($this->daily), null);
        $timelines = $this->observedPublications();

        foreach (array_keys($daily) as $date) {
            $values = [];

            foreach ($timelines as $publication) {
                if (! isset($publication->snapshots[$date])) {
                    continue 2;
                }

                $values[] = $publication->snapshots[$date];
            }

            $daily[$date] = $values === [] ? null : array_sum($values);
        }

        return $daily;
    }
}
