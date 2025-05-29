<?php

namespace App\Tasks;

use App\Classes\Generators\PromptGenerator\PromptGenerator;
use App\Models\Project;

class CreateBasePrompt extends Task
{
    public function __construct(private readonly PromptGenerator $promptGenerator)
    {
    }

    public function run(Project $project): string
    {
        $history = $project->prompts()
            ->latest()
            ->limit(50)
            ->pluck('prompt')
            ->map(fn(string $p): string => "- $p")
            ->join("\n");

        $fullPrompt = <<<TEXT
{$project->prompt}

Here are the previously used prompts:
$history

Generate **one new** “What if...” visual query in the style of Midjourney Shorts.
Just the result, no explanation.
TEXT;

        return $this->promptGenerator->generate($fullPrompt);
    }
}
