<?php

declare(strict_types=1);

namespace App\Tasks\Dashboard;

use App\Data\MetricPeriodData;
use App\Data\PublicationMetricsData;
use App\Data\PublicationTimelineData;
use App\Enums\PublicationCounter;
use App\Models\ChannelMetricSync;
use App\Models\OpusClipMetric;
use App\Models\User;
use App\Repositories\OpusMetricRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\PublicationObservationRepository;
use Carbon\CarbonImmutable;

final readonly class GetPublicationMetrics
{
    public function __construct(private OpusMetricRepository $metrics, private PublicationObservationRepository $observations, private ProjectRepository $projects) {}

    public function run(User $owner, MetricPeriodData $filters): PublicationMetricsData
    {
        $end = CarbonImmutable::now('UTC')->startOfDay();
        $start = $end->subDays($filters->days);
        $publications = [];
        $daily = [];
        $updated = null;

        for ($date = $start->addDay(); $date <= $end; $date = $date->addDay()) {
            $daily[$date->toDateString()] = null;
        }

        $summaries = array_fill_keys(array_column(PublicationCounter::cases(), 'value'), null);
        $sources = [
            $this->metrics->forDashboard($owner, $filters, $start->toDateString(), $end->toDateString()),
            $this->observations->period($owner, $filters, $start->toDateString(), $end->toDateString()),
        ];
        $series = [];

        foreach ($sources as $sourceIndex => $rows) {
            foreach ($rows as $row) {
                $key = serialize([$row->platform, $row->account, $row->media_id]);
                $title = $row instanceof OpusClipMetric ? ($row->clip_title ?? $row->media_id) : $row->title;
                $publications[$key] ??= new PublicationTimelineData($row->platform, $row->account, $row->media_id, $title);
                $updated = max($updated, $row->updated_at?->toIso8601String());

                foreach (PublicationCounter::cases() as $counter) {
                    $value = $row->metrics[$counter->value] ?? null;

                    if ($value !== null) {
                        $series[$key][$counter->value][$sourceIndex][$row->observed_on] = $value;
                    }
                }
            }
        }

        foreach ($series as $key => $counters) {
            foreach ($counters as $counter => $readers) {
                $legacy = $readers[0] ?? [];
                $native = $readers[1] ?? [];
                $snapshots = count($native) >= count($legacy) ? $native : $legacy;

                if ($counter === $filters->metric->value) {
                    $publications[$key]->snapshots = $snapshots;
                }

                if (count($snapshots) >= 2) {
                    $summaries[$counter] = ($summaries[$counter] ?? 0) + end($snapshots) - reset($snapshots);
                }
            }
        }

        foreach ($publications as $publication) {
            $previous = null;

            foreach ($publication->snapshots as $date => $value) {
                if ($previous !== null) {
                    $change = $value - $publication->snapshots[$previous];
                    $publication->intervals[] = ['from' => $previous, 'to' => $date, 'change' => $change];
                    $publication->change = ($publication->change ?? 0) + $change;

                    if (CarbonImmutable::parse($previous, 'UTC')->addDay()->toDateString() === $date) {
                        $daily[$date] = ($daily[$date] ?? 0) + $change;
                    }
                }

                $previous = $date;
            }
        }

        $coverage = $this->observations->coverage($owner, $filters)->map(static fn(ChannelMetricSync $sync): array => [
            'name' => $sync->name, 'status' => $sync->status->label(), 'last_success_at' => $sync->last_success_at?->toIso8601String(),
        ])->values()->all();

        uasort($publications, static fn(PublicationTimelineData $left, PublicationTimelineData $right): int => (($right->change !== null) <=> ($left->change !== null)) ?: ($right->change <=> $left->change));

        return new PublicationMetricsData($filters, $start->toDateString(), $end->toDateString(), $updated, $summaries[$filters->metric->value], $summaries, $daily, array_values($publications), array_values($coverage), $this->projects->analyticsOptions($owner));
    }
}
