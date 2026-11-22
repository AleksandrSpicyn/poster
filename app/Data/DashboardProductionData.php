<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class DashboardProductionData extends Data
{
    public function __construct(
        public int $projects,
        public int $openEpisodes,
        public ?string $latestGeneration,
        public ?string $generationStage,
        public ?string $latestPublication,
    ) {}
}
