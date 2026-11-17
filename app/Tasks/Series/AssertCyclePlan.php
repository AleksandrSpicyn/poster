<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Series\CyclePlanData;
use App\Enums\CycleStructure;
use App\Enums\TransitionMode;
use App\Models\ProductionRun;
use App\Support\Integrations\Payload;
use Illuminate\Validation\ValidationException;

class AssertCyclePlan
{
    public function run(CyclePlanData $plan, ProductionRun $run): void
    {
        if (count($plan->parts) !== data_get($run->input_snapshot, 'episode_limit')
            || $plan->structure !== CycleStructure::from(Payload::string(data_get($run->input_snapshot, 'specification.cycle_structure', 'continuous_story'), 'cycle_structure'))) {
            throw ValidationException::withMessages(['cycle_plan' => 'The generated plan does not match the pinned cycle.']);
        }

        foreach ($plan->parts as $index => $part) {
            $previous = $plan->parts[$index - 1] ?? null;

            if (($index > 0 && data_get($run->input_snapshot, 'specification.scene_mode') === 'same_scene' && $part->transition_mode === TransitionMode::NEW_SCENE)
                || $part->number !== $index + 1 || ($previous === null && $part->transition_mode !== TransitionMode::NEW_SCENE)
                || ($previous !== null && (($part->scene_key !== $previous->scene_key) !== ($part->transition_mode === TransitionMode::NEW_SCENE)))) {
                throw ValidationException::withMessages(['cycle_plan' => 'The cycle plan contains an invalid order or scene boundary.']);
            }
        }
    }
}
