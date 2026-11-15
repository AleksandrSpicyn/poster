<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Enums\ChannelType;
use App\Models\Project;

class CopyPublishingChannels
{
    public function run(Project $project, int $sourceProjectId): void
    {
        if ($sourceProjectId === $project->id) {
            return;
        }

        $source = Project::query()->findOrFail($sourceProjectId);

        foreach ($source->channels()->whereIn('type', [ChannelType::YOUTUBE, ChannelType::INSTAGRAM])->get() as $channel) {
            if (! filled($channel->credentials)) {
                continue;
            }

            $project->channels()->firstOrCreate([
                'type' => $channel->type, 'name' => $channel->name, 'client_id' => $channel->client_id,
            ], ['client_secret' => $channel->client_secret, 'credentials' => $channel->credentials]);
        }
    }
}
