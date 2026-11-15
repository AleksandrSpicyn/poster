<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RevisionStatus;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EpisodeRevision> */
class EpisodeRevisionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'episode_id' => Episode::factory(),
            'number' => fake()->unique()->numberBetween(1, 1000000),
            'status' => RevisionStatus::DRAFT,
            'title' => fake()->words(3, true),
            'objective' => fake()->sentence(),
            'context_snapshot' => [],
            'schema_version' => 1,
            'base_continuity_version' => 0,
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
