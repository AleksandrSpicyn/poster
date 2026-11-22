<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\GetProjectsListAction;
use App\Data\GetProjectsListData;
use App\Enums\SortDirection;
use App\Http\Resources\ProjectResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

class IndexController
{
    /**
     * Список проектов
     *
     * Общие проекты кабинета. Нужны сессия и подтверждённый email.
     * HTML при обычной навигации; JSON при корректных Inertia headers.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @queryParam search string Поиск по названию и базовому промпту, до 200 символов. Example: Animals
     * @queryParam sort string Порядок создания: asc или desc, по умолчанию desc. Example: desc
     * @queryParam perPage integer Размер страницы от 1 до 100, по умолчанию 10. Example: 10
     * @queryParam page integer Номер страницы от 1. Example: 1
     * @response 200 {"component":"Projects/Index","props":{"projects":{"data":[],"meta":{"current_page":1,"per_page":10,"total":0}},"filters":{"search":"","sort":"desc"}}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 422 {"message":"The selected sort is invalid.","errors":{"sort":["The selected sort is invalid."]}}
     */
    public function __invoke(#[CurrentUser] User $owner, GetProjectsListData $data, GetProjectsListAction $action): Response
    {
        return inertia('Projects/Index', [
            'projects' => ProjectResource::collection($action->run($owner, $data)),
            'filters' => ['search' => $data->search ?? '', 'sort' => ($data->sort ?? SortDirection::DESC)->value,
                ...($data->active ? ['active' => true] : []), ...($data->updated ? ['updated' => true] : [])],
            'sortDirections' => SortDirection::labels(),
        ]);
    }
}
