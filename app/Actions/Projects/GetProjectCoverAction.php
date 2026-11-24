<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Project;

final class GetProjectCoverAction
{
    public function run(Project $project): string
    {
        $path = $project->coverImage()?->getPath();
        abort_if($path === null || ! is_file($path), 404);

        return $path;
    }
}
