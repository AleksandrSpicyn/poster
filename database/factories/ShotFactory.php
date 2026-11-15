<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransitionMode;
use App\Models\Scene;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shot> */
class ShotFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'scene_id' => Scene::factory(),
            'position' => 1,
            'duration_ms' => 5000,
            'action' => fake()->sentence(),
            'camera' => [],
            'dialogue' => [],
            'transition_mode' => TransitionMode::NEW_SCENE,
            'source_anchor' => [],
        ];
    }
}
