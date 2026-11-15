<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\SaveProjectAction;
use App\Data\ProjectData;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UpdateController
{
    /**
     * Изменение проекта
     *
     * Требуются сессия, подтверждённый email и CSRF. История и каналы сохраняются.
     * Для Inertia PATCH redirect имеет HTTP 303, для обычного form submit — 302.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @urlParam project integer required ID проекта. Example: 1
     * @bodyParam name string required Название до 255 символов. Example: Wildlife
     * @bodyParam prompt string required Базовый промпт до 10000 символов. Example: Original short stories about animals
     * @response 302
     * @response 303
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422 {"message":"Enter a project name.","errors":{"name":["Enter a project name."]}}
     */
    public function __invoke(Project $project, ProjectData $data, SaveProjectAction $action): RedirectResponse
    {
        $action->run($data, $project);
        Inertia::flash('success', 'Project updated.');

        return to_route('projects.show', $project);
    }
}
