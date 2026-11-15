<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Jobs\AdvanceProductionJob;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\User;
use App\Tasks\Production\CreateProductionRun;
use App\Tasks\Production\GetProductionTargets;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

readonly class StartProductionAction
{
    public function __construct(private GetProductionTargets $targets, private CreateProductionRun $create) {}

    /** @return Collection<int, ProductionRun> */
    public function run(Project $project, string $requestKey, ?User $actor = null, ?int $seriesId = null): Collection
    {
        /** @var Collection<int, ProductionRun> $runs */
        $runs = collect();

        foreach ($this->targets->run($project, $seriesId, $actor) as $target) {
            if (! $actor instanceof User && ! Gate::forUser($target['actor'])->allows('manageSeries')) {
                continue;
            }

            Gate::forUser($target['actor'])->authorize('manageSeries');
            $run = $this->create->run($project, $target['series'], $target['actor'], $requestKey);

            if (! $run instanceof ProductionRun) {
                continue;
            }

            $runs->push($run);

            if ($run->wasRecentlyCreated) {
                AdvanceProductionJob::dispatch($run->id)->afterCommit();
            }
        }

        return $runs;
    }
}
