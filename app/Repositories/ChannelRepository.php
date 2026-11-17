<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Episodes\PublishEpisodeData;
use App\Data\PublishVideoData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/** @extends EloquentRepository<Channel> */
readonly class ChannelRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Channel::class;
    }

    /** @return LengthAwarePaginator<int, Channel> */
    public function paginate(): LengthAwarePaginator
    {
        return Channel::query()->with('projects:id,name')->withCount('posts', 'publicationRequests')->orderBy('name')->orderBy('id')->paginate(20);
    }

    /** @return Collection<int, Channel> */
    public function forSelection(): Collection
    {
        return Channel::query()->orderBy('name')->orderBy('id')->get();
    }

    public function forManagement(Channel $channel): Channel
    {
        return $channel->load('projects:id,name')->loadCount('posts', 'publicationRequests');
    }

    /** @param list<int> $ids */
    public function syncProject(Project $project, array $ids): void
    {
        $project->channels()->sync($ids);
    }

    /** @return list<int> */
    public function selectedIds(Project $project): array
    {
        return array_values($project->channels()->allRelatedIds()->map(fn($id): int => (int) $id)->all());
    }

    public function belongsToProject(Channel $channel, int $projectId): bool
    {
        return $channel->projects()->whereKey($projectId)->exists();
    }

    public function readyForProject(int $channelId, int $projectId): bool
    {
        return Channel::query()->whereKey($channelId)->where('is_active', true)->whereNotNull('credentials->access_token')
            ->whereHas('projects', fn(Builder $query) => $query->whereKey($projectId))->exists();
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
    public function selectedPublishingChannels(int $projectId, PublishEpisodeData $data): Collection
    {
        return Channel::query()->whereHas('projects', fn(Builder $query) => $query->whereKey($projectId))
            ->whereIn('id', $data->channel_ids)->where('is_active', true)->whereNotNull('credentials->access_token')
            ->whereIn('type', [ChannelType::YOUTUBE, ChannelType::INSTAGRAM])->get();
    }

    public function forPublication(PublicationRequest $request): Channel
    {
        return $request->channel()->firstOrFail();
    }

    public function isProjectActive(Channel $channel, Prompt|PublishVideoData $source): bool
    {
        return Channel::query()->whereKey($channel->id)->where('is_active', true)->whereNotNull('credentials->access_token')
            ->whereHas('projects', function (Builder $query) use ($source): void {
                $query->where('is_active', true);

                if ($source instanceof Prompt) {
                    $query->whereKey($source->project_id);

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
