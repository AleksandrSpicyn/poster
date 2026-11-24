<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\GenerationAccountingMode;
use App\Enums\ProjectContentMode;
use App\Enums\SeriesOutputMode;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use App\Repositories\ProductionBudgetRepository;
use App\Repositories\ProductionRunRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductionRun
{
    public function __construct(
        private readonly ProductionBudgetRepository $budget,
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
     * @param list<int>|null $channelIds
     * @param list<int> $ids
     * @return Collection<int, ProductionRun>
     */
    public function forTargets(Project $project, array $ids, User $actor, string $requestKey, bool $onlyFirstRun = false, bool $scheduled = false, ?array $channelIds = null): Collection
    {
        return DB::transaction(function () use ($project, $ids, $actor, $requestKey, $onlyFirstRun, $scheduled, $channelIds): Collection {
            $project = $this->projects->lockForProduction($project->id);
            $metered = $project->getAttribute('generation_accounting_mode') === GenerationAccountingMode::ENFORCED->value;

            if (! $project->is_active || $project->isClippingProject() || $project->owner_id !== $actor->id) {
                return new Collection();
            }

            if (($project->content_mode === ProjectContentMode::SERIES) !== ($ids !== [])) {
                throw ValidationException::withMessages(['project' => 'The project content type changed. Refresh before starting production.']);
            }

            if ($channelIds !== null && ($ids === [] || array_diff($channelIds, $this->runs->deliveryChannelIds($project)) !== [])) {
                throw ValidationException::withMessages(['channel_ids' => 'Выберите доступные каналы этого проекта.']);
            }

            if ($ids === []) {
                $key = hash('sha256', $project->id . ':short:' . $requestKey);

                $existing = $this->runs->forRequestKey($key) ?? $this->runs->blockingForProject($project, null);

                if ($existing instanceof ProductionRun) {
                    return new Collection([$existing]);
                }

                $run = $this->runs->createQueued($project, null, $actor, $key);

                if ($metered) {
                    $this->budget->prepare($project, $run, null);
                }

                return new Collection([$run]);
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
                } elseif ($this->isDue($series, $onlyFirstRun, $channelIds !== null && ! $scheduled)) {
                    $pending[$key] = $series;
                }
            }

            if ($pending !== []) {
                foreach ($this->runs->createQueuedSeries($project, $actor, $pending, $channelIds) as $run) {
                    if ($metered) {
                        $this->budget->prepare($project, $run, $pending[$run->request_key]);
                    }

                    $result[$run->series_id] = $run;
                }
            }

            ksort($result);

            return new Collection(array_values($result));
        }, 3);
    }

    private function isDue(Series $series, bool $onlyFirstRun, bool $manual): bool
    {
        if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO && ($series->episode_limit === null || $series->episode_limit < 1 || $series->episode_limit > 20)) {
            throw ValidationException::withMessages(['series' => 'Configure a finite single-video cycle before starting.']);
        }

        if (($onlyFirstRun && $series->getAttribute('production_runs_exists')) || (! $series->automation_enabled && ! $manual) || $series->archived_at !== null) {
            return false;
        }

        if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO) {
            return $series->cycle_paused_at === null && $series->release_started_at === null && ! $series->getAttribute('final_production_completed');
        }

        $latest = $series->episodes->first();

        return $series->episode_limit === null || $latest?->accepted_assembly_id === null || $latest->number < $series->episode_limit;
    }
}
