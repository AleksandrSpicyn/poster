<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\PromptGenerator\PromptGenerator;
use App\Models\Project;
use App\Models\Prompt;

/**
 * Формирует контекст новой идеи из описания проекта и последних 50 prompts.
 * История помогает генератору избегать повторов, но не гарантирует уникальность.
 */
readonly class CreateBasePrompt
{
    public function __construct(private PromptGenerator $promptGenerator) {}

    public function run(Project $project): string
    {
        $history = $project->prompts()
            ->latest()
            ->limit(50)
            ->get(['prompt'])
            ->map(fn(Prompt $prompt): string => $prompt->prompt)
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
