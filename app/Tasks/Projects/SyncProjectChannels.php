<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;
use App\Repositories\ChannelRepository;

readonly class SyncProjectChannels
{
    public function __construct(private ChannelRepository $channels) {}

    /** @param list<int> $ids */
    public function run(Project $project, array $ids): void
    {
        $this->channels->syncProject($project, $ids);
    }
}
