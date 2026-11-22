<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Data\Production\ProductionTargetData;
use App\Enums\ProjectContentMode;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\UserRepository;
use Illuminate\Validation\ValidationException;

readonly class GetProductionTargets
{
    public function __construct(
        private ProjectRepository $projects,
        private UserRepository $users,
        private SeriesRepository $series,
    ) {}

    /** @return list<ProductionTargetData> */
    public function run(Project $project, ?int $seriesId, ?User $actor, bool $manual = false): array
    {
        $scheduled = ! $actor instanceof User && $seriesId === null && $project->relationLoaded('automatedSeries') && $project->relationLoaded('owner');

        if (! $scheduled) {
            $this->projects->refresh($project);
        }

        if ($project->isClippingProject()) {
            if ($actor instanceof User) {
                throw ValidationException::withMessages(['project' => 'This project imports clips from OpusClip.']);
            }

            return [];
        }

        if (! $project->is_active) {
            if ($actor instanceof User) {
                throw ValidationException::withMessages(['project' => 'Enable this project before starting production.']);
            }

            return [];
        }

        if ($project->content_mode === ProjectContentMode::SHORT) {
            if ($seriesId !== null) {
                throw ValidationException::withMessages(['series_id' => 'Select a series project before requesting an episode.']);
            }

            $actor ??= $scheduled ? $project->owner : ($project->owner_id === null ? null : $this->users->find($project->owner_id));

            return $actor instanceof User ? [new ProductionTargetData(null, $actor)] : [];
        }

        $items = $scheduled ? $project->automatedSeries : $this->series->productionTargets($project, $seriesId);

        return array_values($items->filter(fn(Series $series): bool => $manual || $series->automation_enabled)
            ->map(function (Series $series) use ($actor): ?ProductionTargetData {
                $operator = $actor ?? $series->automationUser;

                return $operator instanceof User ? new ProductionTargetData($series, $operator) : null;
            })
            ->filter()
            ->values()
            ->all());
    }
}
