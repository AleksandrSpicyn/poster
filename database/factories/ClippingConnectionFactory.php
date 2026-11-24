<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClippingDriver;
use App\Models\ClippingConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClippingConnection> */
class ClippingConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->word(),
            'driver' => ClippingDriver::OPUS_CLIP->value,
            'api_key' => fake()->uuid(),
        ];
    }
}
