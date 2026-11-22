<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => $this->faker->word(),
            'prompt' => $this->faker->sentence(),
        ];
    }
}
