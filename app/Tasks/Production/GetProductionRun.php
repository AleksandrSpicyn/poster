<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Models\ProductionRun;
use App\Repositories\ProductionRunRepository;

class GetProductionRun
{
    public function __construct(private readonly ProductionRunRepository $productionRuns) {}

    public function run(int $id): ProductionRun
    {
        return $this->productionRuns->forProduction($id);
    }
}
