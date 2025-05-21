<?php

namespace Database\Factories;

use App\Enums\ChannelType;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'type' => $this->faker->randomElement(
                array_column(ChannelType::cases(), 'value'),
            ),
            'client_id' => $this->faker->word(),
            'client_secret' => $this->faker->word(),
            'project_id' => Project::factory(),
        ];
    }
}
