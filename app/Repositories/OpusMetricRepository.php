<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\MetricPeriodData;
use App\Enums\ChannelType;
use App\Models\OpusClip;
use App\Models\OpusClipMetric;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final readonly class OpusMetricRepository
{
    /** @return Collection<int, OpusClipMetric> */
    public function observations(Project $project, string $day): Collection
    {
        return OpusClipMetric::query()->whereRelation('clip.source', 'project_id', $project->id)
            ->where('observed_on', $day)->with('clip.source')->get();
    }

    /** @return LazyCollection<int, OpusClipMetric> */
    public function forDashboard(User $owner, MetricPeriodData $filters, string $from, string $to): LazyCollection
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
            ->where(fn($query) => $query->whereNotNull('opus_clip_metrics.account_id')->orWhereNotNull('assigned.account'))
            ->when($filters->project, fn($query) => $query->where('projects.id', $filters->project))
            ->whereBetween('opus_clip_metrics.observed_on', [$from, $to])
            ->when($filters->platform instanceof ChannelType, fn($query) => $query->where('opus_clip_metrics.platform', $filters->platform?->value))
            ->select('opus_clip_metrics.*', 'opus_clips.title as clip_title')
            ->selectRaw('coalesce(opus_clip_metrics.account_id, assigned.account) as account')
            ->orderBy('observed_on')->orderBy('opus_clip_metrics.updated_at')->orderBy('opus_clip_metrics.id')->cursor();
    }

    /** @param array<string, int|float> $metrics */
    public function observe(OpusClip $clip, ChannelType $platform, string $mediaId, array $metrics, ?string $accountId = null): void
    {
        OpusClipMetric::query()->updateOrCreate([
            'opus_clip_id' => $clip->id, 'platform' => $platform->value, 'media_id' => $mediaId,
            'observed_on' => now('UTC')->toDateString(),
        ], ['metrics' => $metrics, 'account_id' => $accountId]);
    }
}
