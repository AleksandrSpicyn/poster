<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Models\ProductionRun;
use App\Models\User;
use App\Tasks\Production\StopProductionRun;
use App\Tasks\Users\RefreshUser;
use Illuminate\Support\Facades\Gate;

readonly class StopProductionAction
{
    public function __construct(private StopProductionRun $stop, private RefreshUser $refreshUser) {}

    public function run(ProductionRun $run, User $actor): ProductionRun
    {
        Gate::forUser($this->refreshUser->run($actor))->authorize('manageSeries');

        return $this->stop->run($run);
    }
}
