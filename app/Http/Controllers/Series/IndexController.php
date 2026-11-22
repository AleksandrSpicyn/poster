<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\Series\GetSeriesListAction;
use App\Data\Series\GetSeriesListData;
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
     * @queryParam active boolean Только действующие сериалы активных проектов. Example: 1
     * @queryParam updated boolean Сначала недавно изменённые сериалы. Example: 1
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(#[CurrentUser] User $owner, GetSeriesListData $data, GetSeriesListAction $action): Response
    {
        return inertia('Series/Index', [
            'series' => SeriesResource::collection($action->run($owner, $data)),
            'filters' => ['active' => $data->active, 'updated' => $data->updated],
        ]);
    }
}
