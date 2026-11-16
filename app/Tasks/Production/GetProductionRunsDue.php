<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Models\ProductionRun;
use App\Repositories\ProductionRunRepository;
use Illuminate\Database\Eloquent\Collection;

class GetProductionRunsDue
{
    public function __construct(private readonly ProductionRunRepository $productionRuns) {}

    /** @return Collection<int, ProductionRun> */
    public function run(int $limit): Collection
    {
        return $this->productionRuns->due($limit);
    }
}
