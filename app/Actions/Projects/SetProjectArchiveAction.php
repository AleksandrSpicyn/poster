<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\ProjectArchiveData;
use App\Models\Project;
use App\Tasks\Projects\SetProjectArchive;

final readonly class SetProjectArchiveAction
{
    public function __construct(private SetProjectArchive $archive) {}

    public function run(Project $project, ProjectArchiveData $data): void
    {
        $this->archive->run($project, $data->archived);
    }
}
