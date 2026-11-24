<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\GetPromptsListData;
use App\Data\Projects\ProjectWorkspaceData;
use App\Models\Project;
use App\Models\User;
use App\Tasks\OpusClip\GetOpusClips;
use App\Tasks\Projects\GetProject;
use App\Tasks\Prompts\GetPromptsList;
use App\Tasks\Series\GetSeries;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

readonly class GetProjectAction
{
    public function __construct(private GetProject $getProject, private GetPromptsList $getPrompts, private GetSeries $getSeries, private GetOpusClips $getClips) {}

    public function run(User $owner, Project $project, GetPromptsListData $filters): ProjectWorkspaceData
    {
        $project = $this->getProject->run($project);
        $series = Gate::forUser($owner)->allows('manageSeries') ? $project->seriesDefinition : null;
        $details = $series === null ? null : $this->getSeries->run($series, $filters->page ?? 1);

        $canReadClips = $project->isClippingProject() && Gate::forUser($owner)->allows('manageChannels');

        return new ProjectWorkspaceData(
            project: $project,
            prompts: $this->getPrompts->run($owner, $filters, $project),
            series: $details->series ?? null,
            characters: $details->characters ?? null,
            episodes: $details->episodes ?? new Collection(),
            clips: $canReadClips ? $this->getClips->run($project, $filters) : null,
            trends: $canReadClips ? $this->getClips->trends($project) : [],
        );
    }
}
