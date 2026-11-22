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

        if ($actor instanceof User) {
            Gate::forUser($actor)->authorize('manageSeries', $project);
        }

        $targets = $this->targets->run($project, $seriesId, $actor);
        $ids = [];
        $operator = null;

        foreach ($targets as $target) {
            if ($target->actor->id !== $project->owner_id || ! Gate::forUser($target->actor)->allows('manageSeries')) {
                continue;
            }

            $operator = $target->actor;

            if ($target->series !== null) {
                $ids[] = $target->series->id;
            }
        }

        if ($operator === null) {
            return $runs;
        }

        $runs = $this->create->forTargets($project, $ids, $operator, $requestKey, scheduled: ! $actor instanceof User);

        foreach ($runs as $run) {
            if ($run->wasRecentlyCreated) {
                AdvanceProductionJob::dispatch($run->id)->afterCommit();
            }
        }

        return $runs;
    }
}
