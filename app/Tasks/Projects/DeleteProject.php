<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class DeleteProject
{
    public function run(Project $project): bool
    {
        return DB::transaction(function () use ($project): bool {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($project->prompts()->exists() || $project->channels()->exists()) {
                return false;
            }

            return $project->delete() === true;
        });
    }
}
