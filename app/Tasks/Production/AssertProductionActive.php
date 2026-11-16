<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Models\ProductionRun;
use App\Models\Project;
use App\Repositories\ProductionRunRepository;
use App\Repositories\ProjectRepository;
use Illuminate\Validation\ValidationException;

readonly class AssertProductionActive
{
    public function __construct(private ProjectRepository $projects, private ProductionRunRepository $runs) {}

    public function run(Project $project, ?ProductionRun $run): void
    {
        if (! $this->projects->refresh($project)->is_active || ($run instanceof ProductionRun && ! $this->runs->refresh($run)->status->isActive())) {
            throw ValidationException::withMessages(['project' => 'Enable this project and start a new production before generating content.']);
        }
    }
}
