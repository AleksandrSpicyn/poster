<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Models\ProductionRun;
use App\Models\User;
use App\Tasks\Production\StopProductionRun;
use Illuminate\Support\Facades\Gate;

readonly class StopProductionAction
{
    public function __construct(private StopProductionRun $stop) {}

    public function run(ProductionRun $run, User $actor): ProductionRun
    {
        Gate::forUser($actor->fresh())->authorize('manageSeries');

        return $this->stop->run($run);
    }
}
