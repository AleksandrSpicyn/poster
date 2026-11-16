<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Support\Facades\DB;

class DeleteProject
{
    public function __construct(private readonly ProjectRepository $projects) {}

    public function run(Project $project): bool
    {
        return DB::transaction(function () use ($project): bool {
            $project = $this->projects->findLockedOrFail($project->id);

            if ($this->projects->hasDependencies($project)) {
                return false;
            }

            return $this->projects->delete($project);
        });
    }
}
