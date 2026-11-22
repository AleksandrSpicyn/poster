<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Channel $channel): void {
            if ($channel->project_id !== null) {
                $channel->projects()->syncWithoutDetaching([$channel->project_id]);
            }
        });
    }

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
            'owner_id' => fn(array $attributes) => Project::query()->whereKey($attributes['project_id'])->value('owner_id'),
        ];
    }
}
