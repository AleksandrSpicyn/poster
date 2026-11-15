<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Data\ProjectData;
use App\Enums\ProjectContentMode;
use App\Models\Project;
use Illuminate\Validation\ValidationException;

class SaveProject
{
    public function run(ProjectData $data, ?Project $project = null): Project
    {
        $project ??= new Project();

        if ($project->exists) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
        }

        if ($project->exists && $data->content_mode === ProjectContentMode::SHORT && $project->series()->exists()) {
            throw ValidationException::withMessages(['content_mode' => 'A project with series must keep the series content type.']);
        }

        $project->fill(['name' => $data->name, 'prompt' => $data->prompt, 'content_mode' => $data->content_mode]);
        $project->save();

        return $project;
    }
}
