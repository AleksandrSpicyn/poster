<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Classes\Generators\PromptGenerator\PromptGenerator;
use App\Models\Project;
use App\Repositories\PromptRepository;
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
        private PromptRepository $prompts,
    ) {}

    public function run(Project $project): string
    {
        $history = $this->prompts->recentIdeas($project)
            ->map(fn(string $prompt): string => "- $prompt")
            ->join("\n");

        $fullPrompt = <<<TEXT
{$project->prompt}

Previously used stories, provided only to avoid reusing their plots:
$history

Create one original visual micro-story consistent with the project topic above for a {$this->generalSettings->duration}-second vertical short.
Start with "What if..." and name the subject and specific setting. Return exactly three compact English sentences, at most 85 words total, describing visible present-tense events.

Sentence 1 — visual hook: Open on an unusual situation already going wrong. Show the subject's concrete goal and a moving obstacle or imminent comic setback in the first frame. The viewer should immediately wonder whether the subject can succeed; a surprising animal in a pretty location is not enough.
Sentence 2 — escalation: The subject takes one decisive physical action or makes one simple change of tactic, producing a clear consequence that leads to the payoff. Keep the action readable within the available duration instead of stacking extra obstacles or repeating one motion.
Sentence 3 — payoff: Resolve the original goal with a surprising, funny reversal in the final beat. The ending must be caused by the subject's actions, clearly visible, and different from the obvious expected result.

Keep the complete hook, escalation, and payoff achievable within {$this->generalSettings->duration} seconds in one location and one continuous shot. Use one main subject and at most two relevant props. Use a single readable cause-and-effect chain with harmless comic stakes, leaving time to register the ending. Each action must produce a visible change; repeated attempts and a final reaction alone are not a story. Keep anatomy and object behavior physically coherent; no injury, cruelty, morphing, elaborate choreography, or national stereotypes.
The story must work with sound off, without captions, dialogue, or an explanation. Reject scenic portraits, aimless dancing, and ideas that only involve an animal blowing, spinning, or pushing a toy. Vary the goal, obstacle, action mechanism, and reversal from the history, not just the animal or country. A new location, object or title does not make the same causal plot original. Previously used props are not obligations to reuse or unresolved mysteries to continue.
Silently compare three premises with different goals and action mechanisms, then choose the strongest first-frame hook and clearest action-driven payoff. Reject a candidate if its sequence can be obtained from a previous story by swapping nouns. Check that the final visible state answers the opening goal and follows from the subject's choice. Return only the chosen three-sentence story, with no headings, production notes, hashtags, camera directions, or quality tags.
TEXT;

        return $this->promptGenerator->generate($fullPrompt);
    }
}
