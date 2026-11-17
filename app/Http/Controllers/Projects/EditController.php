<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\GetProjectFormAction;
use App\Data\ProjectData;
use App\Enums\ContentAudience;
use App\Enums\CycleSceneMode;
use App\Enums\CycleStructure;
use App\Enums\ProjectContentMode;
use App\Enums\SeriesAudioMode;
use App\Enums\SeriesOutputMode;
use App\Http\Resources\ChannelResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SeriesResource;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class EditController
{
    /**
     * Форма редактирования проекта
     *
     * Требуются сессия и подтверждённый email. Credentials каналов не возвращаются.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @response 200 {"component":"Projects/Form","props":{"project":{"id":1,"name":"Wildlife","prompt":"Original short stories about animals"},"limits":{"name":255,"prompt":10000}}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, GetProjectFormAction $action): Response
    {
        $details = $action->run($project);
        $series = Gate::allows('manageSeries') ? $project->seriesDefinition : null;

        return inertia('Projects/Form', [
            'project' => ProjectResource::make($details->project)->resolve(),
            'contentModes' => Gate::allows('manageSeries') ? ProjectContentMode::labels() : [ProjectContentMode::SHORT->value => ProjectContentMode::SHORT->label()],
            'series' => $series === null ? null : SeriesResource::make($series)->resolve(),
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
