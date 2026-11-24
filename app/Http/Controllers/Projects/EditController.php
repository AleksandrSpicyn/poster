<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\GetProjectFormAction;
use App\Http\Resources\ProjectFormResponse;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function __invoke(#[CurrentUser] User $owner, Project $project, GetProjectFormAction $action): Response
    {
        $details = $action->run($owner, $project);

        return ProjectFormResponse::render($details, Gate::forUser($owner)->allows('manageSeries'));
    }
}
