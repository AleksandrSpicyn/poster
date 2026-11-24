<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\GetPromptsListData;
use App\Data\OpusClip\GamingTrendReport;
use App\Data\Series\SeriesWorkspaceData;
use App\Models\OpusClip;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use App\Tasks\OpusClip\GetOpusClips;
use App\Tasks\Projects\GetProject;
use App\Tasks\Prompts\GetPromptsList;
use App\Tasks\Series\GetSeries;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

readonly class GetProjectAction
{
    public function __construct(private GetProject $getProject, private GetPromptsList $getPrompts, private GetSeries $getSeries, private GetOpusClips $getClips) {}

    public function run(Project $project): Project
    {
        return $this->getProject->run($project);
    }

    /** @return LengthAwarePaginator<int, Prompt> */
    public function prompts(User $owner, Project $project, GetPromptsListData $filters): LengthAwarePaginator
    {
        return $this->getPrompts->run($owner, $filters, $project);
    }

    public function series(User $owner, Project $project, int $page): ?SeriesWorkspaceData
    {
        $series = Gate::forUser($owner)->allows('manageSeries') ? $project->seriesDefinition : null;

        return $series === null ? null : $this->getSeries->run($series, $page);
    }

    /** @return LengthAwarePaginator<int, OpusClip>|null */
    public function clips(User $owner, Project $project, GetPromptsListData $filters): ?LengthAwarePaginator
    {
        return $this->canReadClips($owner, $project) ? $this->getClips->run($project, $filters) : null;
    }

    /** @return list<GamingTrendReport> */
    public function trends(User $owner, Project $project): array
    {
        return $this->canReadClips($owner, $project) ? $this->getClips->trends($project) : [];
    }

    private function canReadClips(User $owner, Project $project): bool
    {
        return $project->isClippingProject() && Gate::forUser($owner)->allows('manageChannels');
    }
}
