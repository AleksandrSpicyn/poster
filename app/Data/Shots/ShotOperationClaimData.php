<?php

declare(strict_types=1);

namespace App\Data\Shots;

use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use Spatie\LaravelData\Data;

class ShotOperationClaimData extends Data
{
    public function __construct(
        public GenerationOperation $operation,
        public ?Project $project,
        public ?ProductionRun $productionRun,
        public Prompt $prompt,
    ) {}
}
