<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssemblyStatus;
use App\Models\Series;
use App\Models\SeriesAssembly;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SeriesAssembly> */
class SeriesAssemblyFactory extends Factory
{
    public function definition(): array
    {
        return ['series_id' => Series::factory(), 'number' => 1, 'status' => AssemblyStatus::DRAFT,
            'manifest' => [], 'manifest_hash' => hash('sha256', '[]')];
    }
}
