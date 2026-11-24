<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductionRun> */
class ProductionRunFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'actor_id' => fn(array $attributes) => Project::query()->whereKey($attributes['project_id'])->value('owner_id'),
            'request_key' => (string) Str::uuid(),
            'status' => ProductionStatus::QUEUED,
            'stage' => ProductionStage::PREPARING,
            'next_check_at' => now(),
        ];
    }
}
