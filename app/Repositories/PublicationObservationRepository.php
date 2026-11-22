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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final readonly class PublicationObservationRepository
{
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
        return PublicationObservation::query()->select('publication_observations.*')->addSelect([
            'post_url' => Post::query()->select('url')->whereColumn('id', 'publication_observations.post_id')
                ->whereRelation('channel', 'owner_id', $owner->id)->limit(1),
            'clip_publications' => OpusClip::query()->select('publications')->whereColumn('id', 'publication_observations.opus_clip_id')
                ->whereRelation('source.project', 'owner_id', $owner->id)->limit(1),
        ])->withCasts(['clip_publications' => 'array'])->where('owner_id', $owner->id)->whereBetween('observed_on', [$from, $to])
            ->when($filters->platform, fn($query) => $query->where('platform', $filters->platform?->value))
            ->when($filters->project, fn($query) => $query->whereIn('publication_key', DB::table('publication_metric_origins')
                ->where('owner_id', $owner->id)->where('project_id', $filters->project)->select('publication_key')))
            ->orderBy('observed_on')->orderBy('id')->cursor();
    }

    /** @param array<string, int|float> $metrics */
    public function record(Channel $channel, MetricTargetData $target, string $account, array $metrics): bool
    {
        return DB::transaction(function () use ($channel, $target, $account, $metrics): bool {
            User::query()->whereKey($channel->owner_id)->lockForUpdate()->firstOrFail(['id']);
            $current = Channel::query()->where('owner_id', $channel->owner_id)->where('type', $channel->type)->where('is_active', true)->lockForUpdate()->find($channel->id);

            if ($current === null || ($current->credentials['channel_id'] ?? null) !== $account || blank($current->credentials['access_token'] ?? null)
                || ($current->type === ChannelType::TIKTOK && ! $current->hasTiktokScope('video.list'))) {
                return false;
            }

            $identity = hash('sha256', serialize([$channel->type->value, $account, $target->mediaId]));
            $observation = PublicationObservation::query()->firstOrNew([
                'owner_id' => $channel->owner_id, 'publication_key' => $identity, 'observed_on' => now('UTC')->toDateString(),
            ]);
            $linked = false;

            foreach ($target->origins as $origin) {
                if (! Project::query()->whereKey($origin->projectId)->where('owner_id', $channel->owner_id)->exists()) {
                    continue;
                }

                DB::table('publication_metric_origins')->upsert([[
                    'owner_id' => $channel->owner_id, 'publication_key' => $identity, 'source_type' => $origin->type,
                    'source_id' => $origin->id, 'project_id' => $origin->projectId,
                ]], ['owner_id', 'publication_key', 'source_type', 'source_id'], ['project_id']);

                if ($origin->type === Post::class) {
                    $observation->post_id = $origin->id;
                } else {
                    $observation->opus_clip_id = $origin->id;
                }

                $linked = true;
            }

            if (! $linked) {
                return false;
            }

            $observation->fill([
                'channel_id' => $channel->id, 'platform' => $channel->type->value, 'account' => $account,
                'media_id' => $target->mediaId, 'title' => mb_substr($target->title, 0, 255), 'metrics' => $metrics,
            ])->save();

            return true;
        }, attempts: 3);
    }
}
