<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;
use App\Models\Prompt;
use App\Repositories\ProjectRepository;

readonly class GetPromptProject
{
    public function __construct(private ProjectRepository $projects) {}

    public function run(Prompt $prompt): ?Project
    {
        return $this->projects->forPromptPublication($prompt);
    }
}
