<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\Projects\ProjectFormData;
use App\Data\Projects\ProjectScheduleSettingsData;
use App\Models\Project;
use App\Models\User;
use App\Tasks\Channels\GetChannels;
use App\Tasks\Projects\GetProjectSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

readonly class GetProjectFormAction
{
    public function __construct(private GetProjectSettings $getProject, private GetChannels $channels) {}

    public function run(User $owner, ?Project $project = null): ProjectFormData
    {
        $canManage = Gate::forUser($owner)->allows('manageChannels');

        return new ProjectFormData(
            project: $project instanceof Project ? $this->getProject->run($project) : null,
            channels: $canManage ? $this->channels->selection($owner) : new Collection(),
            selectedChannelIds: $canManage && $project instanceof Project ? $this->channels->selectedIds($project) : [],
            scheduleSettings: $project instanceof Project ? ProjectScheduleSettingsData::forProject($project) : null,
        );
    }
}
