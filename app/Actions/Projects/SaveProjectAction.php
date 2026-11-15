<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\ProjectData;
use App\Models\Project;
use App\Tasks\Projects\SaveProject;

readonly class SaveProjectAction
{
    public function __construct(private SaveProject $saveProject) {}

    public function run(ProjectData $data, ?Project $project = null): Project
    {
        return $this->saveProject->run($data, $project);
    }
}
