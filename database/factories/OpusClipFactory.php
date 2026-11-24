<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OpusClip;
use App\Models\OpusSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OpusClip> */
class OpusClipFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'opus_source_id' => OpusSource::factory(),
            'clip_id' => fake()->uuid(),
            'title' => fake()->sentence(),
            'publications' => [],
        ];
    }
}
