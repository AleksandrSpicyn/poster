<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\DeleteProjectAction;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DestroyController
{
    /**
     * Удаление пустого проекта
     *
     * Требуются сессия, подтверждённый email и CSRF. Проект с каналами или
     * генерациями сохраняется целиком: browser получает redirect и flash error,
     * JSON-запрос — HTTP 409. Inertia DELETE redirect имеет HTTP 303.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @urlParam project integer required ID проекта. Example: 1
     * @response 302
     * @response 303
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 409 {"message":"Удаление недоступно, пока у проекта есть связанные данные или активное производство."}
     */
    public function __invoke(Project $project, DeleteProjectAction $action): RedirectResponse
    {
        $action->run($project);

        Inertia::flash('success', 'Проект удалён.');

        return to_route('projects.index');
    }
}
