<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\GetProjectFormAction;
use App\Http\Resources\ProjectFormResponse;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function __invoke(#[CurrentUser] User $owner, GetProjectFormAction $action): Response
    {
        $details = $action->run($owner);

        return ProjectFormResponse::render($details, Gate::forUser($owner)->allows('manageSeries'));
    }
}
