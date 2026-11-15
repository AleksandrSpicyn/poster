<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EpisodeValidity;
use App\Models\Episode;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Episode> */
class EpisodeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'series_id' => Series::factory(),
            'number' => fake()->unique()->numberBetween(1, 1000000),
            'title' => fake()->words(3, true),
            'lock_version' => 0,
            'validity' => EpisodeValidity::CURRENT,
        ];
    }
}
