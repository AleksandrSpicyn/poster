<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\MetricPeriodData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\OpusClipMetric;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final readonly class OpusMetricRepository
{
    public function __construct(private ClippingSyncRunRepository $syncRuns) {}

    /** @return Collection<int, object{platform: string, game: ?string, clip_title: string, growth: int|float|string|null, views: int|float|string|null}> */
    public function topViewGrowth(Project $project, string $start, string $end): Collection
    {
        $views = "NULLIF(opus_clip_metrics.metrics ->> '$.views', 'null') + 0";
        $paired = DB::table('opus_clip_metrics')
            ->join('opus_clips', 'opus_clips.id', '=', 'opus_clip_metrics.opus_clip_id')
            ->join('opus_sources', 'opus_sources.id', '=', 'opus_clips.opus_source_id')
            ->where('opus_sources.project_id', $project->id)->whereIn('observed_on', [$start, $end])
            ->groupBy('platform', 'media_id', 'opus_clips.id', 'opus_clips.clip_id', 'opus_clips.title', 'opus_sources.game')
            ->select('platform', 'media_id', 'opus_clips.id as clip_key', 'opus_sources.game')
            ->selectRaw('coalesce(opus_clips.title, opus_clips.clip_id) as clip_title')
            ->selectRaw("max(case when observed_on = ? then $views end) as baseline", [$start])
            ->selectRaw("max(case when observed_on = ? then $views end) as views", [$end]);

        $ranked = DB::query()->fromSub($paired, 'pairs')->select('pairs.*')->selectRaw('views - baseline as growth')
            ->selectRaw('row_number() over (partition by platform order by views - baseline desc, clip_key, media_id) as position');

        /** @var Collection<int, object{platform: string, game: ?string, clip_title: string, growth: int|float|string|null, views: int|float|string|null}> */
        return DB::query()->fromSub($ranked, 'ranked')->where('position', '<=', 10)
            ->orderBy('platform')->orderBy('position')->get(['platform', 'game', 'clip_title', 'growth', 'views']);
    }

    /** @return LazyCollection<int, OpusClipMetric> */
    public function forDashboard(User $owner, MetricPeriodData $filters, string $from, string $to, ?Channel $channel = null): LazyCollection
    {
        // Historical observations did not store a channel ID. Only resolve an unambiguous assigned account.
        $accounts = DB::table('channel_project')->join('channels', 'channels.id', '=', 'channel_project.channel_id')
            ->where('channels.owner_id', $owner->id)->whereNotNull('channels.external_account')
            ->groupBy('channel_project.project_id', 'channels.type')->havingRaw('count(*) = 1')
            ->select('channel_project.project_id', 'channels.type')->selectRaw('min(channels.external_account) as account');

        return OpusClipMetric::query()
            ->join('opus_clips', 'opus_clips.id', '=', 'opus_clip_metrics.opus_clip_id')
            ->join('opus_sources', 'opus_sources.id', '=', 'opus_clips.opus_source_id')
            ->join('projects', 'projects.id', '=', 'opus_sources.project_id')
            ->leftJoinSub($accounts, 'assigned', fn(JoinClause $join) => $join->on('assigned.project_id', '=', 'projects.id')->on('assigned.type', '=', 'opus_clip_metrics.platform'))
            ->where('projects.owner_id', $owner->id)
            ->when($channel, fn($query, Channel $selected) => $query->where('opus_clip_metrics.platform', $selected->type->value)
                ->whereIn(DB::raw('coalesce(opus_clip_metrics.account_id, assigned.account)'), $selected->metricAccounts()))
            ->where(fn($query) => $query->whereNotNull('opus_clip_metrics.account_id')->orWhereNotNull('assigned.account'))
            ->when($filters->project, fn($query) => $query->where('projects.id', $filters->project))
            ->whereBetween('opus_clip_metrics.observed_on', [$from, $to])
            ->when($filters->platform instanceof ChannelType, fn($query) => $query->where('opus_clip_metrics.platform', $filters->platform?->value))
            ->when($filters->format, fn($query) => $query->where('projects.content_mode', $filters->format?->value))
            ->select('opus_clip_metrics.*', 'projects.id as report_project_id', 'opus_clips.title as clip_title', 'opus_clips.publications as clip_publications')
            ->withCasts(['clip_publications' => 'array'])
            ->selectRaw('coalesce(opus_clip_metrics.account_id, assigned.account) as account')
            ->orderBy('observed_on')->orderBy('opus_clip_metrics.updated_at')->orderBy('opus_clip_metrics.id')->cursor();
    }

    /** @param list<array{opus_clip_id: int, platform: string, media_id: string, observed_on: string, metrics: array<string, int|float>, account_id: string|null}> $rows */
    public function observe(array $rows): void
    {
        $existing = OpusClipMetric::query()->whereIn('opus_clip_id', array_column($rows, 'opus_clip_id'))
            ->whereIn('platform', array_unique(array_column($rows, 'platform')))
            ->whereIn('media_id', array_unique(array_column($rows, 'media_id')))
            ->whereIn('observed_on', array_unique(array_column($rows, 'observed_on')))
            ->get()->keyBy(fn(OpusClipMetric $row): string => serialize([
                $row->opus_clip_id,
                $row->platform,
                $row->media_id,
                $row->observed_on,
            ]));
        $changed = [];
        $newOrigins = [];

        foreach ($rows as $row) {
            $key = serialize([
                $row['opus_clip_id'],
                $row['platform'],
                $row['media_id'],
                $row['observed_on'],
            ]);
            $metric = $existing->get($key) ?? new OpusClipMetric();
            $metric->fill($row);

            if (! $metric->exists) {
                $observationKey = ClippingSyncRunRepository::observationKey($row['platform'], $row['account_id'] ?? '', $row['media_id'], $row['observed_on']);
                $newOrigins[$observationKey][] = $row['opus_clip_id'];
            }

            if ($metric->isDirty()) {
                $changed[$key] = array_diff_key($metric->getAttributes(), array_flip([
                    'id',
                    'created_at',
                    'updated_at',
                ]));
            }
        }

        DB::transaction(function () use ($changed, $newOrigins): void {
            OpusClipMetric::query()->upsert(array_values($changed), [
                'opus_clip_id',
                'platform',
                'media_id',
                'observed_on',
            ], ['metrics', 'account_id']);
            $this->syncRuns->observations($newOrigins);
        }, attempts: 3);
    }
}
