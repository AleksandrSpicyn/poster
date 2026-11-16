<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;
use App\Repositories\ProjectRepository;

class GetProjectSettings
{
    public function __construct(private readonly ProjectRepository $projects) {}

    public function run(Project $project): Project
    {
        return $this->projects->forSettings($project);
    }
}
