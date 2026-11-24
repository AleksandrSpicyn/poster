<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\ProjectData;
use App\Data\Projects\ProjectFormData;
use App\Enums\ContentAudience;
use App\Enums\CycleSceneMode;
use App\Enums\CycleStructure;
use App\Enums\ProjectContentMode;
use App\Enums\SeriesAudioMode;
use App\Enums\SeriesOutputMode;
use App\Models\Project;
use Inertia\Response;

final class ProjectFormResponse
{
    public static function render(ProjectFormData $details, bool $canManageSeries): Response
    {
        $series = $canManageSeries ? $details->project?->seriesDefinition : null;

        return inertia('Projects/Form', [
            'project' => $details->project instanceof Project ? ProjectResource::make($details->project)->resolve() : null,
            'contentModes' => $canManageSeries ? ProjectContentMode::labels() : [ProjectContentMode::SHORT->value => ProjectContentMode::SHORT->label()],
            'series' => $series === null ? null : SeriesResource::make($series)->resolve(),
            ...($details->project instanceof Project ? [
                'scheduleSettings' => $details->scheduleSettings,
                'castCharacters' => $series === null ? [] : CharacterResource::collection($series->characters)->resolve(),
            ] : []),
            'channels' => ChannelResource::collection($details->channels)->resolve(),
            'selectedChannelIds' => $details->selectedChannelIds,
            'audiences' => ContentAudience::labels(),
            'audioModes' => SeriesAudioMode::labels(),
            'outputModes' => SeriesOutputMode::labels(),
            'cycleStructures' => CycleStructure::labels(),
            'sceneModes' => CycleSceneMode::labels(),
            'defaultAudioMode' => SeriesAudioMode::NATIVE->value,
            'limits' => ['name' => ProjectData::NAME_MAX_LENGTH, 'prompt' => ProjectData::PROMPT_MAX_LENGTH],
        ]);
    }
}
