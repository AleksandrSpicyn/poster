<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProjectCovers;

use App\Actions\Projects\GetProjectCoverAction;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShowController extends Controller
{
    /** Получить приватную обложку своего проекта. */
    public function __invoke(Project $project, GetProjectCoverAction $action): BinaryFileResponse
    {
        return $this->privateFile($action->run($project));
    }
}
