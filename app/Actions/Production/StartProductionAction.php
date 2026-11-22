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
use Illuminate\Validation\ValidationException;

readonly class StartProductionAction
{
    public function __construct(private GetProductionTargets $targets, private CreateProductionRun $create) {}

    /**
     * @param list<int>|null $channelIds
     * @return Collection<int, ProductionRun>
     */
    public function run(Project $project, string $requestKey, ?User $actor = null, ?int $seriesId = null, ?array $channelIds = null): Collection
    {
        /** @var Collection<int, ProductionRun> $runs */
        $runs = collect();

        if ($actor instanceof User) {
            Gate::forUser($actor)->authorize('manageSeries', $project);
        }

        if ($channelIds !== null && $seriesId === null) {
            throw ValidationException::withMessages(['series_id' => 'Выберите сериал для этих получателей.']);
        }

        $channelIds = $channelIds === null ? null : array_map(intval(...), $channelIds);
        $targets = $this->targets->run($project, $seriesId, $actor, manual: $actor instanceof User && $channelIds !== null);
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

        $runs = $this->create->forTargets($project, $ids, $operator, $requestKey, scheduled: ! $actor instanceof User, channelIds: $channelIds);

        foreach ($runs as $run) {
            if ($run->wasRecentlyCreated) {
                AdvanceProductionJob::dispatch($run->id)->afterCommit();
            }
        }

        return $runs;
    }
}
