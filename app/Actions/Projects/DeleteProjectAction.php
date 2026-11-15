<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Project;
use App\Tasks\Projects\DeleteProject;

readonly class DeleteProjectAction
{
    public function __construct(private DeleteProject $deleteProject) {}

    public function run(Project $project): bool
    {
        return $this->deleteProject->run($project);
    }
}
