<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProjectContentMode;
use App\Enums\SeriesOutputMode;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use App\Repositories\ProductionRunRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductionRun
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly SeriesRepository $series,
        private readonly ProductionRunRepository $runs,
    ) {}

    public function run(Project $project, ?Series $series, User $actor, string $requestKey, bool $onlyFirstRun = false, bool $scheduled = false): ?ProductionRun
    {
        return $this->forTargets($project, $series instanceof Series ? [$series->id] : [], $actor, $requestKey, $onlyFirstRun, $scheduled)->first();
    }

    /**
     * An empty list represents the single short-video target.
     *
     * @param list<int> $ids
     * @return Collection<int, ProductionRun>
     */
    public function forTargets(Project $project, array $ids, User $actor, string $requestKey, bool $onlyFirstRun = false, bool $scheduled = false): Collection
    {
        return DB::transaction(function () use ($project, $ids, $actor, $requestKey, $onlyFirstRun, $scheduled): Collection {
            $project = $this->projects->findLockedOrFail($project->id);

            if (! $project->is_active || $project->isClippingProject() || $project->owner_id !== $actor->id) {
                return new Collection();
            }

            if (($project->content_mode === ProjectContentMode::SERIES) !== ($ids !== [])) {
                throw ValidationException::withMessages(['project' => 'The project content type changed. Refresh before starting production.']);
            }

            if ($ids === []) {
                $key = hash('sha256', $project->id . ':short:' . $requestKey);

                return new Collection([$this->runs->forRequestKey($key) ?? $this->runs->blockingForProject($project, null)
                    ?? $this->runs->createQueued($project, null, $actor, $key)]);
            }

            $targets = $this->series->lockProductionTargets($project, $ids, $onlyFirstRun);
            abort_unless($targets->count() === count($ids), 404);
            $keys = $targets->mapWithKeys(fn(Series $series): array => [$series->id => hash('sha256', $project->id . ':' . $series->id . ':' . $requestKey)])->all();
            $existing = $this->runs->byRequestKeys(array_values($keys));
            $pending = [];
            $result = [];

            foreach ($targets as $series) {
                if ($scheduled && $series->automation_user_id !== $actor->id) {
                    continue;
                }

                $key = $keys[$series->id];
                $run = $existing->get($key) ?? $series->activeProductionRun;

                if ($run !== null) {
                    $result[$series->id] = $run;
                } elseif ($this->isDue($series, $onlyFirstRun)) {
                    $pending[$key] = $series;
                }
            }

            if ($pending !== []) {
                foreach ($this->runs->createQueuedSeries($project, $actor, $pending) as $run) {
                    $result[$run->series_id] = $run;
                }
            }

            ksort($result);

            return new Collection(array_values($result));
        });
    }

    private function isDue(Series $series, bool $onlyFirstRun): bool
    {
        if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO && ($series->episode_limit === null || $series->episode_limit < 1 || $series->episode_limit > 20)) {
            throw ValidationException::withMessages(['series' => 'Configure a finite single-video cycle before starting.']);
        }

        if (($onlyFirstRun && $series->getAttribute('production_runs_exists')) || ! $series->automation_enabled || $series->archived_at !== null) {
            return false;
        }

        if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO) {
            return $series->cycle_paused_at === null && $series->release_started_at === null;
        }

        $latest = $series->episodes->first();

        return $series->episode_limit === null || $latest?->accepted_assembly_id === null || $latest->number < $series->episode_limit;
    }
}
