<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;
use App\Repositories\ProjectRepository;

final readonly class SetProjectArchive
{
    public function __construct(private ProjectRepository $projects) {}

    public function run(Project $project, bool $archived): void
    {
        $this->projects->setArchived($project, $archived);
    }
}
