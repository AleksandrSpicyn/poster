<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Actions\Series\SaveSeriesSubAction;
use App\Data\ProjectData;
use App\Data\Series\SeriesData;
use App\Data\Series\SeriesSpecificationData;
use App\Enums\ProjectContentMode;
use App\Models\Project;
use App\Models\User;
use App\Tasks\Production\StopProductionRun;
use App\Tasks\Projects\SaveProject;
use App\Tasks\Projects\SyncProjectChannels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

readonly class SaveProjectAction
{
    public function __construct(private SaveProject $saveProject, private SyncProjectChannels $syncChannels, private SaveSeriesSubAction $saveSeries, private StopProductionRun $stopRun) {}

    public function run(ProjectData $data, ?Project $project = null, ?User $actor = null): Project
    {
        if ($data->content_mode === ProjectContentMode::SERIES) {
            Gate::forUser($actor)->authorize('manageSeries');
        }

        if ($data->channel_ids !== null) {
            Gate::forUser($actor)->authorize('manageChannels');
        }

        return DB::transaction(function () use ($data, $project, $actor): Project {
            $project = $this->saveProject->run($data, $project);

            if ($data->channel_ids !== null) {
                $this->syncChannels->run($project, $data->channel_ids);
            }

            if ($data->content_mode !== ProjectContentMode::SERIES) {
                $this->stopDisabledRuns($project);

                return $project;
            }

            if (! $data->series_specification instanceof SeriesSpecificationData) {
                throw ValidationException::withMessages(['series_specification' => 'Fill in the series settings.']);
            }

            $data->series_specification->premise = $data->prompt;
            $seriesData = new SeriesData(
                name: $data->name,
                specification: $data->series_specification,
                lock_version: $data->series_lock_version,
                automation_enabled: $data->automation_enabled,
                episode_limit: $data->episode_limit,
                output_mode: $data->output_mode,
            );

            try {
                $this->saveSeries->run($project, $seriesData, $project->seriesDefinition, $actor);
            } catch (ValidationException $exception) {
                if (isset($exception->errors()['lock_version'])) {
                    throw ValidationException::withMessages(['series_lock_version' => $exception->errors()['lock_version']]);
                }

                throw $exception;
            }

            $this->stopDisabledRuns($project);

            return $project;
        });
    }

    private function stopDisabledRuns(Project $project): void
    {
        if ($project->is_active) {
            return;
        }

        foreach ($project->productionRuns as $run) {
            $this->stopRun->run($run, 'The project is disabled.');
        }
    }
}
