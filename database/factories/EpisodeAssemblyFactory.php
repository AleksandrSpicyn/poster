<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssemblyStatus;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EpisodeAssembly> */
class EpisodeAssemblyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'episode_revision_id' => EpisodeRevision::factory(),
            'number' => fake()->unique()->numberBetween(1, 1000000),
            'status' => AssemblyStatus::DRAFT,
            'manifest' => [],
            'continuity_snapshot' => [],
            'lock_version' => 0,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn(): array => [
            'status' => AssemblyStatus::READY,
            'completed_at' => now(),
        ]);
    }
}
