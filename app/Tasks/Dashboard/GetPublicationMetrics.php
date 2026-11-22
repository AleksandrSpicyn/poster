<?php

declare(strict_types=1);

namespace App\Tasks\Dashboard;

use App\Data\MetricPeriodData;
use App\Data\PublicationMetricsData;
use App\Data\PublicationTimelineData;
use App\Models\ChannelMetricSync;
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
        $total = null;

        for ($date = $start->addDay(); $date <= $end; $date = $date->addDay()) {
            $daily[$date->toDateString()] = null;
        }

        foreach ($this->metrics->forDashboard($owner, $filters, $start->toDateString(), $end->toDateString()) as $row) {
            $key = serialize([$row->platform, $row->account, $row->media_id]);
            $publication = $publications[$key] ??= new PublicationTimelineData($row->platform, $row->account, $row->media_id, $row->clip_title ?? $row->media_id);
            $updated = max($updated, $row->updated_at?->toIso8601String());
            $value = $row->metrics[$filters->metric->value] ?? null;

            if ($value !== null) {
                $publication->snapshots[$row->observed_on] = $value;
            }
        }

        $native = [];

        foreach ($this->observations->period($owner, $filters, $start->toDateString(), $end->toDateString()) as $row) {
            $key = serialize([$row->platform, $row->account, $row->media_id]);
            $publication = $native[$key] ??= new PublicationTimelineData($row->platform, $row->account, $row->media_id, $row->title);
            $updated = max($updated, $row->updated_at?->toIso8601String());
            $value = $row->metrics[$filters->metric->value] ?? null;

            if ($value !== null) {
                $publication->snapshots[$row->observed_on] = $value;
            }
        }

        // Keep a continuous series from one reader. Differences between API definitions are not daily growth.
        foreach ($native as $key => $publication) {
            if (! isset($publications[$key]) || count($publication->snapshots) >= count($publications[$key]->snapshots)) {
                $publications[$key] = $publication;
            }
        }

        foreach ($publications as $publication) {
            $previous = null;

            foreach ($publication->snapshots as $date => $value) {
                if ($previous !== null) {
                    $change = $value - $publication->snapshots[$previous];
                    $publication->intervals[] = ['from' => $previous, 'to' => $date, 'change' => $change];
                    $publication->change = ($publication->change ?? 0) + $change;
                    $total = ($total ?? 0) + $change;

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

        return new PublicationMetricsData($filters, $start->toDateString(), $end->toDateString(), $updated, $total, $daily, array_values($publications), array_values($coverage), $this->projects->analyticsOptions($owner));
    }
}
