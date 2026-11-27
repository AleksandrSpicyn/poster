<?php

declare(strict_types=1);

namespace App\Http\Controllers\Characters;

use App\Actions\Characters\GetCharacterAction;
use App\Classes\Generators\AudioGenerator\BytePlusSpeech;
use App\Data\Characters\CharacterPageData;
use App\Enums\ReferenceView;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\CharacterRevisionResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SeriesResource;
use App\Models\Character;
use App\Models\Episode;
use App\Models\Project;
use App\Models\Series;
use Inertia\Response;

class ShowController
{
    /**
     * Персонаж, версии внешности и приватные референсы
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Доступ определяется владельцем проекта.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam character integer required ID персонажа. Example: 1
     * @queryParam page integer Страница версий от 1. Example: 1
     * @queryParam episode integer Эпизод этого сериала с сохранённым участием персонажа. Example: 3
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, Character $character, CharacterPageData $data, GetCharacterAction $action, BytePlusSpeech $speech): Response
    {
        $details = $action->run($character, $data);

        return inertia('Characters/Show', [
            'project' => ProjectResource::make($project)->resolve(),
            'series' => SeriesResource::make($series)->resolve(),
            'character' => CharacterResource::make($details->character)->resolve(),
            'selectedEpisode' => $details->selectedEpisode instanceof Episode ? ['id' => $details->selectedEpisode->id, 'number' => $details->selectedEpisode->number] : null,
            'episodesCount' => $details->episodesCount,
            'revisions' => CharacterRevisionResource::collection($details->revisions),
            'referenceViews' => ReferenceView::labels(),
            'speechVoices' => $speech->availableVoices(),
        ]);
    }
}
