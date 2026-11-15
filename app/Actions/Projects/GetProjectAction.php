<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\GetPromptsListData;
use App\Models\Character;
use App\Models\Episode;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Series;
use App\Tasks\Projects\GetProject;
use App\Tasks\Prompts\GetPromptsList;
use App\Tasks\Series\GetSeries;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

readonly class GetProjectAction
{
    public function __construct(private GetProject $getProject, private GetPromptsList $getPrompts, private GetSeries $getSeries) {}

    /** @return array{project: Project, prompts: LengthAwarePaginator<int, Prompt>, series: Series|null, characters: LengthAwarePaginator<int, Character>|null, episodes: Collection<int, Episode>} */
    public function run(Project $project, GetPromptsListData $filters): array
    {
        $project = $this->getProject->run($project);
        $series = Gate::allows('manageSeries') ? $project->seriesDefinition : null;
        $details = $series === null ? null : $this->getSeries->run($series, $filters->page ?? 1);

        return [
            'project' => $project,
            'prompts' => $this->getPrompts->run($filters, $project),
            'series' => $details['series'] ?? null,
            'characters' => $details['characters'] ?? null,
            'episodes' => $details['episodes'] ?? new Collection(),
        ];
    }
}
