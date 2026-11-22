<?php

declare(strict_types=1);

namespace App\Tasks\Dashboard;

use App\Classes\OpusClip\VideoReference;
use App\Data\MetricPeriodData;
use App\Data\PublicationMetricsData;
use App\Data\PublicationTimelineData;
use App\Enums\ChannelType;
use App\Enums\ProjectContentMode;
use App\Enums\PublicationCounter;
use App\Http\Resources\ProjectResource;
use App\Models\OpusClipMetric;
use App\Models\Project;
use App\Models\PublicationObservation;
use App\Models\User;
use App\Repositories\DashboardCatalogRepository;
use App\Repositories\OpusMetricRepository;
use App\Repositories\PublicationObservationRepository;
use Carbon\CarbonImmutable;

final readonly class GetPublicationMetrics
{
    public function __construct(private OpusMetricRepository $metrics, private PublicationObservationRepository $observations, private DashboardCatalogRepository $catalog) {}

    public function run(User $owner, MetricPeriodData $filters, bool $includeProduction = false): PublicationMetricsData
    {
        $end = $filters->to === null ? CarbonImmutable::now('UTC')->startOfDay() : CarbonImmutable::parse($filters->to, 'UTC');
        $start = $filters->from === null ? $end->subDays($filters->days) : CarbonImmutable::parse($filters->from, 'UTC')->subDay();
        $projects = $this->catalog->projects($owner);
        $channels = $this->catalog->channels($owner);
        $included = $channels->whereNotIn('id', $filters->exclude_channels)->keyBy('id');
        $excluded = $channels->whereIn('id', $filters->exclude_channels)->keyBy('id');
        $matchingProjects = $filters->format instanceof ProjectContentMode ? $projects->where('content_mode', $filters->format)->keyBy('id') : null;
        $accounts = [];

        foreach ($included as $channel) {
            $key = $channel->external_account ?? $channel->credentials[$channel->type === ChannelType::INSTAGRAM ? 'account_name' : 'channel_id'] ?? null;

            if (is_string($key)) {
                $accounts[$channel->type->value][$key] = $channel->name;
            }
        }

        $publications = [];
        $daily = [];
        $updated = null;

        $first = $start->addDay();
        $sparse = $first->diffInDays($end) > 366;

        if ($sparse) {
            $daily = [$first->toDateString() => null, $end->toDateString() => null];
        } else {
            for ($date = $first; $date <= $end; $date = $date->addDay()) {
                $daily[$date->toDateString()] = null;
            }
        }

        $summaries = array_fill_keys(array_column(PublicationCounter::cases(), 'value'), null);
        $sources = [
            $this->metrics->forDashboard($owner, $filters, $start->toDateString(), $end->toDateString()),
            $this->observations->period($owner, $filters, $start->toDateString(), $end->toDateString()),
        ];
        $series = [];

        foreach ($sources as $sourceIndex => $rows) {
            foreach ($rows as $row) {
                $nativeChannel = $row instanceof PublicationObservation ? $included->get($row->channel_id) : null;
                $name = $nativeChannel->name ?? $accounts[$row->platform][$row->account] ?? null;

                if (($row instanceof PublicationObservation && $row->channel_id !== null && $excluded->has($row->channel_id)) || ($excluded->isNotEmpty() && $name === null)) {
                    continue;
                }

                $key = serialize([$row->platform, $row->account, $row->media_id]);
                $title = $row instanceof OpusClipMetric ? ($row->clip_title ?? 'Публикация без названия') : $row->title;
                $publications[$key] ??= new PublicationTimelineData($row->platform, $row->account, $row->media_id, $title, projectId: $row->report_project_id, channelName: $name, key: hash('sha256', $key));
                $publication = $publications[$key];
                $platform = ChannelType::tryFrom($row->platform);

                if ($platform !== null) {
                    $postUrl = $row instanceof PublicationObservation ? $row->post_url : null;
                    $publication->url = VideoReference::publicUrl($platform, $row->media_id, [$postUrl, $publication->url, ...array_column($row->clip_publications ?? [], 'url')]);
                }

                $updated = max($updated, $row->updated_at?->toIso8601String());

                foreach ($row->metrics as $metric => $value) {
                    $series[$key][$metric][$sourceIndex][$row->observed_on] = $value;
                }
            }
        }

        $totals = $summaries;

        foreach ($series as $key => $counters) {
            foreach ($counters as $counter => $readers) {
                $legacy = $readers[0] ?? [];
                $native = $readers[1] ?? [];
                $snapshots = $native + $legacy;
                ksort($snapshots);
                $latest = end($snapshots);

                if ($latest !== false && array_key_last($snapshots) !== $start->toDateString()) {
                    $publications[$key]->latest[$counter] = $latest;

                    if (array_key_exists($counter, $totals)) {
                        $totals[$counter] = ($totals[$counter] ?? 0) + $latest;
                    }
                }

                if ($counter === 'estimatedMinutesWatched' && count($snapshots) >= 2) {
                    $publications[$key]->watchMinutesChange = end($snapshots) - reset($snapshots);
                }

                if ($counter === $filters->metric->value) {
                    $publications[$key]->snapshots = $snapshots;
                }

                if (array_key_exists($counter, $summaries) && count($snapshots) >= 2) {
                    $summaries[$counter] = ($summaries[$counter] ?? 0) + end($snapshots) - reset($snapshots);
                }
            }
        }

        foreach ($publications as $publication) {
            $previous = null;

            foreach ($publication->snapshots as $date => $value) {
                if ($sparse && $date >= $first->toDateString()) {
                    $observed = CarbonImmutable::parse($date, 'UTC');

                    foreach ([$observed->subDay(), $observed, $observed->addDay()] as $boundary) {
                        if ($boundary >= $first && $boundary <= $end) {
                            $daily[$boundary->toDateString()] ??= null;
                        }
                    }
                }

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

        ksort($daily);
        $statuses = $this->observations->coverage($owner, $filters)->keyBy('channel_id');
        $channelOptions = [];
        $coverage = [];

        foreach ($channels as $channel) {
            $projectIds = array_values($channel->projects->map(static fn(Project $project): int => $project->id)->all());
            $channelOptions[] = ['id' => $channel->id, 'name' => $channel->name, 'platform' => $channel->type->value, 'projects' => $projectIds];

            if (! $included->has($channel->id) || ($filters->platform instanceof ChannelType && $channel->type !== $filters->platform)
                || ($filters->project !== null && ! in_array($filters->project, $projectIds, true))
                || ($matchingProjects !== null && ! $matchingProjects->hasAny($projectIds))) {
                continue;
            }

            $sync = $statuses->get($channel->id);
            $coverage[] = ['id' => $channel->id, 'name' => $channel->name, 'platform' => $channel->type->value,
                'state' => $sync?->status->value, 'status' => $sync?->status->label() ?? 'Нет наблюдений', 'last_success_at' => $sync?->last_success_at?->toIso8601String()];
        }

        uasort($publications, static fn(PublicationTimelineData $left, PublicationTimelineData $right): int => (($right->change !== null) <=> ($left->change !== null)) ?: ($right->change <=> $left->change));

        $production = null;

        if ($includeProduction) {
            $projectIds = $matchingProjects ?? $projects->keyBy('id');

            if ($filters->project !== null) {
                $projectIds = $projectIds->only([$filters->project]);
            }

            $channelIds = null;

            if ($filters->platform instanceof ChannelType || $excluded->isNotEmpty()) {
                $channelIds = array_column($coverage, 'id');
                $matchingChannels = $included->only($channelIds);
                $projectIds = $projectIds->only($matchingChannels->flatMap(fn($channel) => $channel->projects->modelKeys())->all());
            }

            $production = $this->catalog->production($owner, array_values($projectIds->modelKeys()), $channelIds);
        }

        return new PublicationMetricsData($filters, $start->toDateString(), $end->toDateString(), $updated, $summaries[$filters->metric->value], $summaries, $daily, array_values($publications), $coverage, array_values($projects->map(static fn(Project $project): array => [
            'id' => $project->id,
            'name' => $project->name,
            'cover' => ProjectResource::make($project)->toArray(request())['cover'],
            'is_clipping' => $project->isClippingProject(),
            'content_mode' => $project->content_mode->value,
        ])->all()), $channelOptions, $totals, $production);
    }
}
