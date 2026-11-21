<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OpusSource;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OpusSource> */
class OpusSourceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'external_id' => fake()->uuid(), 'title' => fake()->sentence()];
    }
}
