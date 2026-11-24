<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SeriesAudioMode;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Series> */
class SeriesFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
            'specification' => [
                'premise' => fake()->sentence(),
                'visual_style' => 'animation',
                'language' => 'en',
                'audience' => 'general',
                'episode_duration_seconds' => 30,
                'narrative_rules' => '',
                'audio_mode' => SeriesAudioMode::NATIVE->value,
            ],
            'specification_version' => 1,
            'continuity_version' => 0,
            'lock_version' => 0,
        ];
    }
}
