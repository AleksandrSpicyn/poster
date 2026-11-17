<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Jobs\AdvanceProductionJob;
use App\Models\ProductionRun;
use App\Models\User;
use App\Tasks\Production\ResumeProduction;
use Illuminate\Support\Facades\Gate;

readonly class ResumeProductionAction
{
    public function __construct(private ResumeProduction $resume) {}

    public function run(ProductionRun $run, User $actor): void
    {
        Gate::forUser($actor)->authorize('manageSeries');
        $current = $this->resume->run($run, $actor);
        AdvanceProductionJob::dispatch($current->id)->afterCommit();
    }
}
