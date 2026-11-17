<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Models\ProductionRun;
use App\Repositories\ProductionRunRepository;
use Illuminate\Validation\ValidationException;

readonly class PrepareCyclePlan
{
    public function __construct(private ProductionRunRepository $runs) {}

    /** @return array<string, mixed> */
    public function run(ProductionRun $run): array
    {
        $run = $this->runs->forProduction($run->id);

        if ($run->series?->episode_limit === null || $run->input_snapshot === null) {
            throw ValidationException::withMessages(['cycle' => 'A finite cycle and pinned settings are required.']);
        }

        return $run->input_snapshot;
    }
}
