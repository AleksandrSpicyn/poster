<?php

declare(strict_types=1);

namespace App\Data\Projects;

use App\Models\Channel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

class ProjectFormData extends Data
{
    /** @param Collection<int, Channel> $channels
     * @param list<int> $selectedChannelIds
     */
    public function __construct(public ?Project $project, public Collection $channels, public array $selectedChannelIds, public ?ProjectScheduleSettingsData $scheduleSettings = null) {}
}
