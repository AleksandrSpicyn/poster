<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\PromptGenerator\PromptGenerator;
use App\Models\Project;
use App\Models\Prompt;
use App\Settings\GeneralSettings;

/**
 * Формирует контекст новой идеи из описания проекта и последних 50 prompts.
 * История помогает генератору избегать повторов, но не гарантирует уникальность.
 */
readonly class CreateBasePrompt
{
    public function __construct(
        private PromptGenerator $promptGenerator,
        private GeneralSettings $generalSettings,
    ) {}

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

Create one original visual micro-story consistent with the project topic above for a {$this->generalSettings->duration}-second vertical short.
Start with "What if..." and name the subject and specific setting. Return exactly three compact English sentences, at most 85 words total, describing visible present-tense events.

Sentence 1 — visual hook: Open on an unusual situation already going wrong. Show the subject's concrete goal and a moving obstacle or imminent comic setback in the first frame. The viewer should immediately wonder whether the subject can succeed; a surprising animal in a pretty location is not enough.
Sentence 2 — escalation: The subject takes decisive physical action, the obstacle makes the problem worse, and the subject rapidly improvises. Use two or three clear, connected action beats that visibly change the situation instead of repeating one motion.
Sentence 3 — payoff: Resolve the original goal with a surprising, funny reversal in the final beat. The ending must be caused by the subject's actions, clearly visible, and different from the obvious expected result.

Keep the complete hook, escalation, and payoff achievable within {$this->generalSettings->duration} seconds in one location and one continuous shot. Use one main subject and at most two relevant props. Favor readable movement, pursuit, a near miss, or a last-second save with harmless comic stakes. Keep anatomy and object behavior physically coherent; no injury, cruelty, morphing, elaborate choreography, or national stereotypes.
The story must work with sound off, without captions, dialogue, or an explanation. Reject scenic portraits, aimless dancing, and ideas that only involve an animal blowing, spinning, or pushing a toy. Vary the goal, obstacle, action, and reversal from the history, not just the animal or country.
Silently compare three distinct premises and choose the strongest first-frame hook and clearest action-driven payoff. Return only the chosen three-sentence story, with no headings, production notes, hashtags, camera directions, or quality tags.
TEXT;

        return $this->promptGenerator->generate($fullPrompt);
    }
}
