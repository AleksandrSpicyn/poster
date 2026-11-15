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

Create one original visual micro-story consistent with the project topic above, designed for a single short vertical image-to-video shot.
Start with "What if..." and name the subject and specific setting. Use 2 compact English sentences, at most 60 words total.
The opening must show an immediately readable, surprising contrast. Describe one simple physical action and its visible, playful consequence; both must be achievable in a few seconds in the same location. Put the action and payoff in the second sentence as direct present-tense visual descriptions.
Use one main subject and at most one interacting object. Make the humor understandable with sound off, without captions, dialogue, national stereotypes, danger, or distress. Favor curiosity, harmless surprise, or a satisfying physical interaction over a passive scenic portrait.
Vary the action, setting, contrast, and payoff from the history, not just the animal or country. Avoid transformations, scene changes, elaborate choreography, and ideas that require text to explain them.
Before answering, silently check that the first frame can establish the setup and that the action has a clear visual payoff. Return only the two sentences, with no headings, hashtags, camera directions, or quality tags.
TEXT;

        return $this->promptGenerator->generate($fullPrompt);
    }
}
