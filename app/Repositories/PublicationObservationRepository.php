<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\MetricPeriodData;
use App\Data\MetricTargetData;
use App\Enums\ChannelType;
use App\Enums\MetricCollectionStatus;
use App\Models\Channel;
use App\Models\ChannelMetricSync;
use App\Models\ClippingAccountMapping;
use App\Models\ClippingConnection;
use App\Models\OpusClip;
use App\Models\Post;
use App\Models\Project;
use App\Models\PublicationObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final readonly class PublicationObservationRepository
{
    public function __construct(private ClippingSyncRunRepository $syncRuns) {}

    /** @return LazyCollection<int, Channel> */
    public function channels(): LazyCollection
    {
        return Channel::query()->whereNotNull('owner_id')
            ->where(fn(Builder $query) => $query->whereHas('posts')
                ->orWhereIn('id', ClippingAccountMapping::query()->select('channel_id')))->with('owner')->lazyById();
    }

    public function mark(Channel $channel, MetricCollectionStatus $status): void
    {
        if ($channel->owner_id !== null) {
            ChannelMetricSync::query()->updateOrCreate(['channel_id' => $channel->id], [
                'owner_id' => $channel->owner_id, 'status' => $status,
                ...($status === MetricCollectionStatus::COLLECTED ? ['last_success_at' => now()] : []),
            ]);
        }
    }

    /** @return Collection<int, ChannelMetricSync> */
    public function coverage(User $owner, MetricPeriodData $filters): Collection
    {
        return ChannelMetricSync::query()->join('channels', 'channels.id', '=', 'channel_metric_syncs.channel_id')
            ->where('channels.owner_id', $owner->id)->where('channel_metric_syncs.owner_id', $owner->id)
            ->when($filters->platform, fn($query) => $query->where('channels.type', $filters->platform?->value))
            ->when($filters->project, fn($query) => $query->whereIn('channels.id', DB::table('channel_project')->where('project_id', $filters->project)
                ->whereIn('project_id', Project::query()->where('owner_id', $owner->id)->select('id'))->select('channel_id')))
            ->select('channel_metric_syncs.*', 'channels.name')->orderBy('channels.name')->get();
    }

    /** @return LazyCollection<int, OpusClip> */
    public function importedClips(User $owner, Channel $channel, string $account): LazyCollection
    {
        $connections = ClippingAccountMapping::query()->where('platform', $channel->type->value)
            ->where('channel_id', $channel->id)->where('external_id', $account)
            ->whereIn('clipping_connection_id', ClippingConnection::query()->where('owner_id', $owner->id)->select('id'))
            ->select('clipping_connection_id');

        return OpusClip::query()->whereHas('source', fn(Builder $query) => $query->whereIn('clipping_connection_id', $connections)
            ->whereHas('project', fn(Builder $projects) => $projects->where('owner_id', $owner->id)
                ->whereHas('channels', fn(Builder $channels) => $channels->whereKey($channel->id))))->with('source:id,project_id')->lazyById(100);
    }

    /** @return LazyCollection<int, PublicationObservation> */
    public function period(User $owner, MetricPeriodData $filters, string $from, string $to): LazyCollection
    {
        return PublicationObservation::query()->join('publication_metric_identities as identity', static fn(JoinClause $join) => $join
            ->on('identity.owner_id', '=', 'publication_observations.owner_id')->on('identity.publication_key', '=', 'publication_observations.publication_key'))
            ->select('publication_observations.*', 'identity.platform', 'identity.account', 'identity.media_id')->addSelect([
                'report_project_id' => DB::table('publication_metric_origins as origin')->select('origin.project_id')
                    ->join('projects as origin_project', 'origin_project.id', '=', 'origin.project_id')
                    ->where('origin_project.owner_id', $owner->id)->where('origin.owner_id', $owner->id)
                    ->whereColumn('origin.publication_key', 'publication_observations.publication_key')
                    ->when($filters->project, fn($query) => $query->where('origin.project_id', $filters->project))
                    ->when($filters->format, fn($query) => $query->where('origin_project.content_mode', $filters->format?->value))
                    ->orderBy('origin.project_id')->limit(1),
                'post_url' => Post::query()->select('url')->whereColumn('id', 'publication_observations.post_id')
                    ->whereRelation('channel', 'owner_id', $owner->id)->limit(1),
                'clip_publications' => OpusClip::query()->select('publications')->whereColumn('id', 'publication_observations.opus_clip_id')
                    ->whereRelation('source.project', 'owner_id', $owner->id)->limit(1),
            ])->withCasts(['clip_publications' => 'array'])->where('publication_observations.owner_id', $owner->id)->whereBetween('observed_on', [$from, $to])
            ->when($filters->platform, fn($query) => $query->where('platform', $filters->platform?->value))
            ->when($filters->format, fn($query) => $query->whereIn('publication_observations.publication_key', DB::table('publication_metric_origins as origin')
                ->join('projects', 'projects.id', '=', 'origin.project_id')->where('projects.owner_id', $owner->id)->where('origin.owner_id', $owner->id)
                ->where('projects.content_mode', $filters->format?->value)->when($filters->project, fn($origin) => $origin->where('origin.project_id', $filters->project))->select('origin.publication_key')))
            ->when($filters->project, fn($query) => $query->whereIn('publication_observations.publication_key', DB::table('publication_metric_origins')
                ->where('owner_id', $owner->id)->where('project_id', $filters->project)->select('publication_key')))
            ->orderBy('observed_on')->orderBy('id')->cursor();
    }

    /**
     * @param array<array-key, MetricTargetData> $targets
     * @param array<array-key, array<string, int|float>> $metrics
     */
    public function record(Channel $channel, array $targets, string $account, array $metrics): int
    {
        if ($metrics === []) {
            return 0;
        }

        return DB::transaction(function () use ($channel, $targets, $account, $metrics): int {
            User::query()->whereKey($channel->owner_id)->lockForUpdate()->firstOrFail(['id']);
            $current = Channel::query()->where('owner_id', $channel->owner_id)->where('type', $channel->type)->where('is_active', true)->lockForUpdate()->find($channel->id);

            if ($current === null || ($current->credentials['channel_id'] ?? null) !== $account || blank($current->credentials['access_token'] ?? null)
                || ($current->type === ChannelType::TIKTOK && ! $current->hasTiktokScope('video.list'))) {
                return 0;
            }

            $projectIds = collect($targets)->pluck('origins')->collapse()->pluck('projectId')->unique()->values();
            $owned = Project::query()->where('owner_id', $channel->owner_id)->whereIn('id', $projectIds)->pluck('id')->flip();
            $day = now('UTC')->toDateString();
            $keys = [];

            foreach (array_keys($metrics) as $id) {
                $keys[$id] = hash('sha256', serialize([$channel->type->value, $account, $targets[$id]->mediaId]));
            }
            $existing = PublicationObservation::query()->where('owner_id', $channel->owner_id)->where('observed_on', $day)
                ->whereIn('publication_key', $keys)->get()->keyBy('publication_key');

            $identities = [];
            $links = [];
            $rows = [];
            $newOrigins = [];

            foreach ($metrics as $id => $values) {
                $target = $targets[$id];
                $key = $keys[$id];
                $previous = $existing->get($key);
                $postId = $previous?->post_id;
                $clipId = $previous?->opus_clip_id;
                $linked = false;

                foreach ($target->origins as $origin) {
                    if (! $owned->has($origin->projectId)) {
                        continue;
                    }

                    $links[] = ['owner_id' => $channel->owner_id, 'publication_key' => $key, 'source_type' => $origin->type,
                        'source_id' => $origin->id, 'project_id' => $origin->projectId];

                    if ($origin->type === Post::class) {
                        $postId = $origin->id;
                    } else {
                        $clipId = $origin->id;

                        if ($previous === null) {
                            $observationKey = ClippingSyncRunRepository::observationKey($channel->type->value, $account, $target->mediaId, $day);
                            $newOrigins[$observationKey][] = $origin->id;
                        }
                    }
                    $linked = true;
                }

                if ($linked) {
                    $identity = ['owner_id' => $channel->owner_id, 'publication_key' => $key];
                    $identities[] = [...$identity, 'platform' => $channel->type->value, 'account' => $account, 'media_id' => $target->mediaId];
                    $snapshot = $previous ?? new PublicationObservation();
                    $snapshot->fill([...$identity, 'observed_on' => $day, 'channel_id' => $channel->id, 'title' => mb_substr($target->title, 0, 255),
                        'post_id' => $postId, 'opus_clip_id' => $clipId, 'metrics' => $values]);

                    if ($snapshot->isDirty()) {
                        $rows[] = array_diff_key($snapshot->getAttributes(), array_flip(['id', 'created_at', 'updated_at']));
                    }
                }
            }

            if ($links !== []) {
                DB::table('publication_metric_identities')->upsert($identities, ['owner_id', 'publication_key'], ['publication_key']);
                DB::table('publication_metric_origins')->upsert($links, ['owner_id', 'publication_key', 'source_type', 'source_id'], ['project_id']);
                PublicationObservation::query()->upsert($rows, ['owner_id', 'publication_key', 'observed_on'], ['post_id', 'opus_clip_id', 'title', 'metrics', 'channel_id']);
            }

            $this->syncRuns->observations($newOrigins);

            return count($identities);
        }, attempts: 3);
    }
}
