<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Exceptions\ProjectDeletionBlocked;
use App\Models\Project;
use App\Tasks\Projects\DeleteProject;

readonly class DeleteProjectAction
{
    public function __construct(private DeleteProject $deleteProject) {}

    public function run(Project $project): void
    {
        if (! $this->deleteProject->run($project)) {
            throw new ProjectDeletionBlocked($project);
        }
    }
}
