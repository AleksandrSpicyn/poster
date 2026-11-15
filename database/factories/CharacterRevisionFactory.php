<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RevisionStatus;
use App\Models\Character;
use App\Models\CharacterRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CharacterRevision> */
class CharacterRevisionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'number' => fake()->unique()->numberBetween(1, 1000000),
            'status' => RevisionStatus::DRAFT,
            'description' => fake()->sentence(),
            'appearance' => [],
            'voice' => [],
            'lock_version' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn(): array => [
            'status' => RevisionStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }
}
