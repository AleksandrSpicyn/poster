<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectCovers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Inertia\Response;

class EditController
{
    /** Открыть редактор обложки проекта. */
    public function __invoke(Project $project): Response
    {
        return inertia('Projects/Cover', ['project' => ProjectResource::make($project)->resolve()]);
    }
}
