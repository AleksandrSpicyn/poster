<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Projects\PublishingSourceData;
use App\Models\Project;
use App\Repositories\ProjectRepository;

class GetChannelSources
{
    public function __construct(private readonly ProjectRepository $projects) {}

    /** @return list<PublishingSourceData> */
    public function run(?Project $project = null): array
    {
        return $this->projects->publishingSources($project);
    }
}
