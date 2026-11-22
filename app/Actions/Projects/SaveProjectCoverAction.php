<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\Projects\ProjectCoverData;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use App\Tasks\Projects\SaveProjectCover;
use Illuminate\Support\Facades\DB;

readonly class SaveProjectCoverAction
{
    public function __construct(private ProjectRepository $projects, private SaveProjectCover $saveCover) {}

    public function run(Project $project, ProjectCoverData $data): void
    {
        DB::transaction(function () use ($project, $data): void {
            $locked = $this->projects->findLockedOrFail($project->id);
            $this->saveCover->run($locked, $data);
        });
    }
}
