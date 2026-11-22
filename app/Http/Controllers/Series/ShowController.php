<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\Series\GetSeriesAction;
use App\Data\Series\SeriesCharactersListData;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\EpisodeSummaryResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SeriesResource;
use App\Models\Project;
use App\Models\Series;
use Inertia\Response;

class ShowController
{
    /**
     * Сериал, персонажи и последние эпизоды
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Данные кабинета общие.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @urlParam series integer required ID сериала этого проекта. Example: 1
     * @queryParam page integer Страница персонажей от 1. Example: 1
     * @queryParam with_archived boolean Включить архивных персонажей. Example: 0
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, SeriesCharactersListData $data, GetSeriesAction $action): Response
    {
        $details = $action->run($series, $data->page, $data->with_archived);

        return inertia('Series/Show', [
            'project' => ProjectResource::make($details->series->project)->resolve(),
            'series' => SeriesResource::make($details->series)->resolve(),
            'withArchived' => $data->with_archived,
            'characters' => CharacterResource::collection($details->characters),
            'episodes' => EpisodeSummaryResource::collection($details->episodes)->resolve(),
        ]);
    }
}
