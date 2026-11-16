<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;
use App\Repositories\ChannelRepository;
use App\Repositories\ProjectRepository;

class CopyPublishingChannels
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ChannelRepository $channels,
    ) {}

    public function run(Project $project, int $sourceProjectId): void
    {
        if ($sourceProjectId === $project->id) {
            return;
        }

        $source = $this->projects->findOrFail($sourceProjectId);

        foreach ($this->channels->publishableForProject($source) as $channel) {
            if (! filled($channel->credentials)) {
                continue;
            }

            $this->channels->copyToProject($project, $channel);
        }
    }
}
