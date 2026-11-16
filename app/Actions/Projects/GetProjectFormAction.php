<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\Projects\ProjectFormData;
use App\Models\Project;
use App\Tasks\Projects\GetProjectSettings;
use App\Tasks\Series\GetChannelSources;
use Illuminate\Support\Facades\Gate;

readonly class GetProjectFormAction
{
    public function __construct(private GetProjectSettings $getProject, private GetChannelSources $sources) {}

    public function run(?Project $project = null): ProjectFormData
    {
        return new ProjectFormData(
            project: $project instanceof Project ? $this->getProject->run($project) : null,
            channelSources: Gate::allows('manageSeries') ? $this->sources->run($project) : [],
        );
    }
}
