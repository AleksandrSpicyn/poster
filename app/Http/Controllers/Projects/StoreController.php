<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\SaveProjectAction;
use App\Data\ProjectData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StoreController
{
    /**
     * Создание проекта
     *
     * Требуются сессия, подтверждённый email и CSRF. Создаёт только проект.
     * Browser-валидация возвращает redirect с errors, JSON-вариант — 422.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @bodyParam name string required Название до 255 символов. Example: Wildlife
     * @bodyParam prompt string required Базовый промпт до 10000 символов. Example: Original short stories about animals
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 422 {"message":"Enter a project name.","errors":{"name":["Enter a project name."]}}
     */
    public function __invoke(ProjectData $data, SaveProjectAction $action): RedirectResponse
    {
        $project = $action->run($data);
        Inertia::flash('success', 'Project created.');

        return to_route('projects.show', $project);
    }
}
