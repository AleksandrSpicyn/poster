<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Character;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Character> */
class CharacterFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'series_id' => Series::factory(),
            'name' => fake()->firstName(),
            'lock_version' => 0,
        ];
    }
}
