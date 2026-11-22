<?php

declare(strict_types=1);

namespace App\Http\Controllers\Characters;

use App\Actions\Characters\GetCharacterAction;
use App\Data\PageData;
use App\Enums\ReferenceView;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\CharacterRevisionResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SeriesResource;
use App\Models\Character;
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
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, Character $character, PageData $data, GetCharacterAction $action): Response
    {
        $details = $action->run($character, $data->page);

        return inertia('Characters/Show', [
            'project' => ProjectResource::make($project)->resolve(),
            'series' => SeriesResource::make($series)->resolve(),
            'character' => CharacterResource::make($details->character)->resolve(),
            'episodesCount' => $details->episodesCount,
            'revisions' => CharacterRevisionResource::collection($details->revisions),
            'referenceViews' => ReferenceView::labels(),
        ]);
    }
}
