<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\Series\GetSeriesListAction;
use App\Data\PageData;
use App\Http\Resources\SeriesResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

class IndexController
{
    /**
     * Список сериалов
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Данные кабинета общие.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @queryParam page integer Номер страницы от 1. Example: 1
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(#[CurrentUser] User $owner, PageData $data, GetSeriesListAction $action): Response
    {
        return inertia('Series/Index', ['series' => SeriesResource::collection($action->run($owner, $data->page))]);
    }
}
