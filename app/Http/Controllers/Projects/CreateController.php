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
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class CreateController
{
    /**
     * Форма создания проекта
     *
     * Требуются сессия и подтверждённый email. Не запускает генерацию или публикацию.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @response 200 {"component":"Projects/Form","props":{"project":null,"limits":{"name":255,"prompt":10000}}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     */
    public function __invoke(GetProjectFormAction $action): Response
    {
        $details = $action->run();

        return inertia('Projects/Form', [
            'project' => null,
            'contentModes' => Gate::allows('manageSeries') ? ProjectContentMode::labels() : [ProjectContentMode::SHORT->value => ProjectContentMode::SHORT->label()],
            'series' => null,
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
