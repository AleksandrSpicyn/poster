<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Episodes\PublishEpisodeData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Project;
use App\Models\PublicationRequest;
use Illuminate\Database\Eloquent\Collection;

/** @extends EloquentRepository<Channel> */
readonly class ChannelRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Channel::class;
    }

    /** @return Collection<int, Channel> */
    public function publishableForProject(Project $source): Collection
    {
        return $source->channels()->whereIn('type', [ChannelType::YOUTUBE, ChannelType::INSTAGRAM])->get();
    }

    public function copyToProject(Project $project, Channel $channel): Channel
    {
        return $project->channels()->firstOrCreate([
            'type' => $channel->type, 'name' => $channel->name, 'client_id' => $channel->client_id,
        ], ['client_secret' => $channel->client_secret, 'credentials' => $channel->credentials]);
    }

    /** @return Collection<int, Channel> */
    public function selectedPublishingChannels(int $projectId, PublishEpisodeData $data): Collection
    {
        return Channel::query()->where('project_id', $projectId)
            ->whereIn('id', $data->channel_ids)
            ->whereIn('type', [ChannelType::YOUTUBE, ChannelType::INSTAGRAM])
            ->get();
    }

    public function forPublication(PublicationRequest $request): Channel
    {
        return $request->channel()->firstOrFail();
    }

    public function isProjectActive(Channel $channel): bool
    {
        return $channel->project()->where('is_active', true)->exists();
    }
}
