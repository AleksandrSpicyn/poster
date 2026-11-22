<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Data\ProjectData;
use App\Data\Projects\ProjectScheduleData;
use App\Enums\ProjectContentMode;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectRepository;
use Illuminate\Validation\ValidationException;

class SaveProject
{
    public function __construct(private readonly ProjectRepository $projects) {}

    public function run(ProjectData $data, ?Project $project = null, ?User $actor = null): Project
    {
        if (! $project instanceof Project) {
            $project = new Project();
            $project->owner_id = $actor?->id;
        }

        if ($project->exists) {
            $project = $this->projects->findLockedOrFail($project->id);

            if ($data->settings_version !== null && $data->settings_version !== $project->settings_version) {
                throw ValidationException::withMessages(['settings_version' => 'Серверная версия изменилась.']);
            }

            $project->settings_version++;
        }

        if ($project->exists && $data->content_mode === ProjectContentMode::SHORT && $this->projects->hasSeries($project)) {
            throw ValidationException::withMessages(['content_mode' => 'A project with series must keep the series content type.']);
        }

        $project->fill(['name' => $data->name, 'prompt' => $data->prompt, 'content_mode' => $data->content_mode,
            'is_active' => $data->is_active ?? $project->is_active]);

        if ($data->schedule instanceof ProjectScheduleData) {
            $project->schedule_enabled = $data->schedule->enabled;
            $project->schedule_time = $data->schedule->time;
            $project->schedule_timezone = $data->schedule->timezone;
        }

        $this->projects->save($project);

        if ($data->content_mode === ProjectContentMode::SERIES) {
            $this->projects->forSettings($project);
        }

        return $project->is_active ? $project : $this->projects->withBlockingProductionRuns($project);
    }
}
