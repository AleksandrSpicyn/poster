<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Channels\GetChannelListData;
use App\Data\Episodes\PublishEpisodeData;
use App\Data\PublishVideoData;
use App\Enums\ChannelType;
use App\Enums\SortDirection;
use App\Models\Channel;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** @extends EloquentRepository<Channel> */
readonly class ChannelRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Channel::class;
    }

    /** @return LengthAwarePaginator<int, Channel> */
    public function paginate(User $owner, GetChannelListData $filters): LengthAwarePaginator
    {
        return Channel::query()->where('owner_id', $owner->id)->with(['projects' => fn($query) => $query->where('projects.owner_id', $owner->id)->select('projects.id', 'name')])->withCount('posts', 'publicationRequests')
            ->when(filled($filters->search), fn(Builder $query) => $query->whereLike('name', '%' . $filters->search . '%'))
            ->when($filters->active !== null, fn(Builder $query) => $query->where('is_active', $filters->active))
            ->when(
                $filters->sort,
                fn(Builder $query, SortDirection $sort) => $query->orderBy('created_at', $sort->value),
                fn(Builder $query) => $query->orderBy('name'),
            )
            ->orderBy('id')->paginate($filters->perPage, page: $filters->page)->withQueryString();
    }

    /** @return Collection<int, Channel> */
    public function forSelection(User $owner): Collection
    {
        return Channel::query()->where('owner_id', $owner->id)->orderBy('name')->orderBy('id')->get();
    }

    public function forManagement(Channel $channel): Channel
    {
        return $channel->load(['projects' => fn($query) => $query->where('projects.owner_id', $channel->owner_id)->select('projects.id', 'name')])->loadCount('posts', 'publicationRequests');
    }

    /** @param list<int> $ids */
    public function syncProject(Project $project, array $ids): void
    {
        $ownedIds = Channel::query()->whereNotNull('owner_id')->where('owner_id', $project->owner_id)
            ->whereKey($ids)->orderBy('id')->lockForUpdate()->pluck('id');

        if ($ownedIds->count() !== count($ids)) {
            throw ValidationException::withMessages(['channel_ids' => 'Select channels from your workspace.']);
        }

        $project->channels()->sync($ids);
    }

    /** @return list<int> */
    public function selectedIds(Project $project): array
    {
        return array_values($project->channels()->where('channels.owner_id', $project->owner_id)->get(['channels.id'])->map(fn(Channel $channel): int => $channel->id)->all());
    }

    public function belongsToClippingWorkspace(Channel $channel): bool
    {
        return $channel->projects()->where('clipping_workspace', Project::OPUS_CLIP_WORKSPACE)->exists();
    }

    public function belongsToProject(Channel $channel, int $projectId): bool
    {
        return $channel->projects()->whereKey($projectId)->exists();
    }

    public function readyForProject(int $channelId, int $projectId): bool
    {
        return Channel::query()->whereKey($channelId)->where('is_active', true)->whereNotNull('credentials->access_token')
            ->whereHas('projects', fn(Builder $query) => $query->whereKey($projectId)->whereColumn('projects.owner_id', 'channels.owner_id'))->exists();
    }

    public function hasDependencies(Channel $channel): bool
    {
        return $this->hasHistory($channel) || $channel->projects()->exists();
    }

    public function hasHistory(Channel $channel): bool
    {
        return $channel->posts()->exists() || PublicationRequest::query()->where('channel_id', $channel->id)->exists();
    }

    /** @return Collection<int, Channel> */
    public function selectedPublishingChannels(int $projectId, PublishEpisodeData $data, bool $direct = false): Collection
    {
        return Channel::query()->whereHas('projects', fn(Builder $query) => $query->whereKey($projectId)->whereColumn('projects.owner_id', 'channels.owner_id'))
            ->whereIn('id', $data->channel_ids)->where('is_active', true)->whereNotNull('credentials->access_token')
            ->get()->filter(static fn(Channel $channel): bool => $direct
                ? $channel->canDirectPostTiktok()
                : in_array($channel->type, [ChannelType::YOUTUBE, ChannelType::INSTAGRAM], true) || $channel->canSendTiktokInboxVideo())->values();
    }

    public function forPublication(PublicationRequest $request): Channel
    {
        return $request->channel()->firstOrFail();
    }

    public function isProjectActive(Channel $channel, Prompt|PublishVideoData $source): bool
    {
        return Channel::query()->whereKey($channel->id)->where('is_active', true)->whereNotNull('credentials->access_token')
            ->whereHas('projects', function (Builder $query) use ($source): void {
                $query->where('is_active', true)->whereColumn('projects.owner_id', 'channels.owner_id');

                if ($source instanceof Prompt || $source->short instanceof Prompt) {
                    $query->whereKey($source instanceof Prompt ? $source->project_id : $source->short->project_id);

                    return;
                }

                if ($source->seriesAssemblyId !== null) {
                    $query->whereHas('series.finalAssembly', fn(Builder $query) => $query->whereKey($source->seriesAssemblyId));

                    return;
                }

                $query->whereHas('series.episodes.revisions.assemblies', fn(Builder $query) => $query->whereKey($source->episodeAssemblyId));
            })->exists();
    }
}
