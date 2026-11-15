<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Project;
use App\Tasks\Projects\GetProjectSettings;
use App\Tasks\Series\GetChannelSources;
use Illuminate\Support\Facades\Gate;

readonly class GetProjectFormAction
{
    public function __construct(private GetProjectSettings $getProject, private GetChannelSources $sources) {}

    /** @return array{project: Project|null, channelSources: list<array{id: int, name: string}>} */
    public function run(?Project $project = null): array
    {
        return [
            'project' => $project instanceof Project ? $this->getProject->run($project) : null,
            'channelSources' => Gate::allows('manageSeries') ? $this->sources->run($project) : [],
        ];
    }
}
