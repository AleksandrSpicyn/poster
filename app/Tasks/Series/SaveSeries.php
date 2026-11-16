<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Series\SeriesData;
use App\Enums\ProjectContentMode;
use App\Models\Project;
use App\Models\Series;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Validation\ValidationException;

class SaveSeries
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly SeriesRepository $series,
    ) {}

    public function run(Project $project, SeriesData $data, ?Series $series = null, ?int $actorId = null): Series
    {
        $project = $this->projects->findLockedOrFail($project->id);

        if (! $series instanceof Series && $this->projects->hasSeries($project)) {
            throw ValidationException::withMessages(['series' => 'This project already has a series. Edit its project settings.']);
        }

        if ($series instanceof Series) {
            $series = $this->series->lockedForProject($project->id, $series->id);

            if ($data->lock_version !== $series->lock_version) {
                throw ValidationException::withMessages(['lock_version' => 'The series was changed. Refresh it before saving.']);
            }

            if ($series->archived_at !== null) {
                throw ValidationException::withMessages(['series' => 'An archived series cannot be changed.']);
            }
        }

        $series ??= new Series(['project_id' => $project->id]);

        if ($data->automation_enabled && $actorId === null && $series->automation_user_id === null) {
            throw ValidationException::withMessages(['automation_enabled' => 'Save automation settings while signed in as a series operator.']);
        }

        $specification = $data->specification->toArray();
        $specification['narrative_rules'] = $data->specification->narrative_rules ?? '';

        if ($data->specification->story_outline === null) {
            unset($specification['story_outline']);
        }

        $series->fill([
            'name' => $data->name,
            'specification' => $specification,
            'automation_enabled' => $data->automation_enabled,
            'episode_limit' => $data->episode_limit,
            'automation_user_id' => $data->automation_enabled ? ($actorId ?? $series->automation_user_id) : $series->automation_user_id,
        ]);

        if ($series->exists && $series->isDirty()) {
            $series->lock_version++;

            if ($series->isDirty('specification')) {
                $series->specification_version++;
            }
        }

        $this->series->save($series);
        $this->projects->update($project, ['content_mode' => ProjectContentMode::SERIES]);

        return $this->series->refreshWithActiveRun($series);
    }
}
