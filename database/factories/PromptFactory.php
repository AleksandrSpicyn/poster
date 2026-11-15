<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Prompt;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Prompt> */
class PromptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'prompt' => fake()->sentence(),
            'generated' => false,
        ];
    }

    public function forShot(Shot $shot): static
    {
        $episode = $shot->scene()->firstOrFail()->episodeRevision()->firstOrFail()->episode()->firstOrFail();

        return $this->state([
            'project_id' => $episode->series()->firstOrFail()->project_id,
            'shot_id' => $shot->id,
            'take_number' => 1,
        ]);
    }
}
