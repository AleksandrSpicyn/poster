<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\Series\GetSeriesAction;
use App\Data\PageData;
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
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, PageData $data, GetSeriesAction $action): Response
    {
        $details = $action->run($series, $data->page);

        return inertia('Series/Show', [
            'project' => ProjectResource::make($project)->resolve(),
            'series' => SeriesResource::make($details['series'])->resolve(),
            'characters' => CharacterResource::collection($details['characters']),
            'episodes' => EpisodeSummaryResource::collection($details['episodes'])->resolve(),
        ]);
    }
}
