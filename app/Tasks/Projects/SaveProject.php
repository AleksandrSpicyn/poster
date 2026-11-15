<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Data\ProjectData;
use App\Models\Project;

class SaveProject
{
    public function run(ProjectData $data, ?Project $project = null): Project
    {
        $project ??= new Project();
        $project->fill(['name' => $data->name, 'prompt' => $data->prompt]);
        $project->save();

        return $project;
    }
}
