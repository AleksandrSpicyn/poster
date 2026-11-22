<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectCovers;

use App\Actions\Projects\SaveProjectCoverAction;
use App\Data\Projects\ProjectCoverData;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class StoreController
{
    /** Сохранить изображение и кадрирование обложки проекта. */
    public function __invoke(Project $project, ProjectCoverData $data, SaveProjectCoverAction $action): RedirectResponse
    {
        $action->run($project, $data);

        return to_route('projects.cover.edit', $project);
    }
}
