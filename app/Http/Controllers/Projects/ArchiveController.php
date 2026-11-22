<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\SetProjectArchiveAction;
use App\Data\ProjectArchiveData;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class ArchiveController
{
    /**
     * Изменить состояние архива проекта
     *
     * Владелец может переместить проект в архив или вернуть в библиотеку. История и связи сохраняются.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @urlParam project integer required ID проекта. Example: 1
     * @bodyParam archived boolean required Переместить в архив. Example: true
     * @response 302
     * @response 404
     * @response 422
     */
    public function __invoke(Project $project, ProjectArchiveData $data, SetProjectArchiveAction $action): RedirectResponse
    {
        $action->run($project, $data);
        Inertia::flash('success', $data->archived ? 'Проект перемещён в архив.' : 'Проект возвращён в библиотеку.');

        return to_route('projects.index', $data->archived ? ['archived' => 1] : []);
    }
}
