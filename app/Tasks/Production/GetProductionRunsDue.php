<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use Illuminate\Database\Eloquent\Collection;

class GetProductionRunsDue
{
    /** @return Collection<int, ProductionRun> */
    public function run(int $limit): Collection
    {
        return ProductionRun::query()
            ->whereIn('status', [ProductionStatus::QUEUED, ProductionStatus::RUNNING, ProductionStatus::WAITING])
            ->where('next_check_at', '<=', now())->orderBy('id')->limit($limit)->get();
    }
}
