<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Support\LazyCollection;

readonly class GetActiveProjects
{
    public function __construct(private ProjectRepository $projects) {}

    /** @return LazyCollection<int, Project> */
    public function run(?string $projectId): LazyCollection
    {
        return $this->projects->activeForProduction($projectId);
    }
}
