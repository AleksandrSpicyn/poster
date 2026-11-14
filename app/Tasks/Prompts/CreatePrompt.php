<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Project;
use App\Models\Prompt;

class CreatePrompt
{
    public function run(Project $project, string $idea): Prompt
    {
        /** @var Prompt $prompt */
        $prompt = $project->prompts()->create(['prompt' => $idea]);

        return $prompt;
    }
}
