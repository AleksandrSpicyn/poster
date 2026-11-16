<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\GetPromptsListData;
use App\Data\Projects\ProjectWorkspaceData;
use App\Models\Project;
use App\Tasks\Projects\GetProject;
use App\Tasks\Prompts\GetPromptsList;
use App\Tasks\Series\GetSeries;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

readonly class GetProjectAction
{
    public function __construct(private GetProject $getProject, private GetPromptsList $getPrompts, private GetSeries $getSeries) {}

    public function run(Project $project, GetPromptsListData $filters): ProjectWorkspaceData
    {
        $project = $this->getProject->run($project);
        $series = Gate::allows('manageSeries') ? $project->seriesDefinition : null;
        $details = $series === null ? null : $this->getSeries->run($series, $filters->page ?? 1);

        return new ProjectWorkspaceData(
            project: $project,
            prompts: $this->getPrompts->run($filters, $project),
            series: $details->series ?? null,
            characters: $details->characters ?? null,
            episodes: $details->episodes ?? new Collection(),
        );
    }
}
