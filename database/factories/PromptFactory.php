<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Prompt;
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
}
