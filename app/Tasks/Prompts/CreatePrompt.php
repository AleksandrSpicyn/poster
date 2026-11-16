<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Project;
use App\Models\Prompt;
use App\Repositories\PromptRepository;

class CreatePrompt
{
    public function __construct(private readonly PromptRepository $prompts) {}

    public function run(Project $project, string $idea): Prompt
    {
        return $this->prompts->createForProject($project, $idea);
    }
}
