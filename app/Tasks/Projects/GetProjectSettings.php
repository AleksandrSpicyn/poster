<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;

class GetProjectSettings
{
    public function run(Project $project): Project
    {
        return $project->load('seriesDefinition');
    }
}
