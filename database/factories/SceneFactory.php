<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EpisodeRevision;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Scene> */
class SceneFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'episode_revision_id' => EpisodeRevision::factory(),
            'position' => 1,
            'title' => fake()->words(3, true),
            'setting' => ['place' => 'room', 'time' => 'day'],
            'objective' => fake()->sentence(),
            'initial_state' => [],
            'expected_final_state' => [],
            'is_closed' => false,
        ];
    }
}
