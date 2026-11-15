<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\GetPromptsListData;
use App\Models\Project;
use App\Models\Prompt;
use App\Tasks\Projects\GetProject;
use App\Tasks\Prompts\GetPromptsList;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetProjectAction
{
    public function __construct(private GetProject $getProject, private GetPromptsList $getPrompts) {}

    /** @return array{project: Project, prompts: LengthAwarePaginator<int, Prompt>} */
    public function run(Project $project, GetPromptsListData $filters): array
    {
        return [
            'project' => $this->getProject->run($project),
            'prompts' => $this->getPrompts->run($filters, $project),
        ];
    }
}
