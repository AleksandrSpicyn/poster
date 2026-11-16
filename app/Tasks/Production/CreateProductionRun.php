<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProjectContentMode;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use App\Repositories\EpisodeRepository;
use App\Repositories\ProductionRunRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductionRun
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly SeriesRepository $series,
        private readonly ProductionRunRepository $runs,
        private readonly EpisodeRepository $episodes,
    ) {}

    public function run(Project $project, ?Series $series, User $actor, string $requestKey, bool $onlyFirstRun = false): ?ProductionRun
    {
        return DB::transaction(function () use ($project, $series, $actor, $requestKey, $onlyFirstRun): ?ProductionRun {
            $project = $this->projects->findLockedOrFail($project->id);

            if (! $project->is_active) {
                return null;
            }

            $series = $series instanceof Series ? $this->series->lockedForProject($project->id, $series->id) : null;

            if (($project->content_mode === ProjectContentMode::SERIES) !== ($series instanceof Series)) {
                throw ValidationException::withMessages(['project' => 'The project content type changed. Refresh before starting production.']);
            }

            $key = hash('sha256', $project->id . ':' . ($series->id ?? 'short') . ':' . $requestKey);
            $existing = $this->runs->forRequestKey($key);

            if ($existing instanceof ProductionRun) {
                return $existing;
            }

            $active = $this->runs->blockingForProject($project, $series);

            if ($active instanceof ProductionRun) {
                return $active;
            }

            if ($series instanceof Series) {
                if ($onlyFirstRun && $this->runs->existsForSeries($series)) {
                    return null;
                }

                if (! $series->automation_enabled || $series->archived_at !== null) {
                    return null;
                }

                $latest = $this->episodes->latestForSeries($series);

                if ($series->episode_limit !== null && $latest?->accepted_assembly_id !== null && $latest->number >= $series->episode_limit) {
                    return null;
                }
            }

            return $this->runs->createQueued($project, $series, $actor, $key);
        });
    }
}
