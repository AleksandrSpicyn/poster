<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Classes\Story\Snapshot;
use App\Data\Series\SeriesCastData;
use App\Data\Series\SeriesData;
use App\Enums\ProjectContentMode;
use App\Enums\SeriesOutputMode;
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

        if ($series->exists && $data->output_mode !== $series->output_mode && $this->series->hasEpisodes($series)) {
            throw ValidationException::withMessages(['output_mode' => 'Choose the release mode before creating the first episode.']);
        }

        if ($data->output_mode === SeriesOutputMode::SINGLE_VIDEO && ($data->episode_limit === null || $data->episode_limit > 20
            || $data->episode_limit * $data->specification->episode_duration_seconds > 600)) {
            throw ValidationException::withMessages(['episode_limit' => 'A single video requires 1–20 parts and at most 600 seconds in total.']);
        }

        $specification = $data->specification->toArray();
        $specification['narrative_rules'] = $data->specification->narrative_rules ?? '';

        if ($data->output_mode === SeriesOutputMode::EPISODES) {
            unset($specification['cycle_structure'], $specification['scene_mode']);
        }

        if ($data->specification->story_outline === null) {
            unset($specification['story_outline']);
        }

        if ($series->cycle_plan !== null && (Snapshot::hash($specification) !== Snapshot::hash($series->specification) || $data->episode_limit !== $series->episode_limit)) {
            throw ValidationException::withMessages(['series_specification' => 'The cycle has started. Its plan and duration are pinned to the generated parts.']);
        }

        $cast = $data->cast_settings;

        if ($cast instanceof SeriesCastData) {
            if ($cast->lead_character_id !== null && $cast->lead_character_id !== $series->cast_settings?->lead_character_id
                && (! $series->exists || ! $this->series->hasAvailableCharacter($series, $cast->lead_character_id))) {
                throw ValidationException::withMessages(['cast_settings.lead_character_id' => 'Выберите действующего героя этого сериала с утверждённой версией.']);
            }

            if ($cast->use_series_voice && ! filled($cast->voice_description)) {
                throw ValidationException::withMessages(['cast_settings.voice_description' => 'Опишите голос для следующих эпизодов.']);
            }

            $series->cast_settings = $cast;
        }

        $series->fill([
            'name' => $data->name,
            'output_mode' => $data->output_mode,
            'specification' => $specification,
            'automation_enabled' => $data->automation_enabled,
            'auto_publish' => $data->auto_publish ?? $series->auto_publish,
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
