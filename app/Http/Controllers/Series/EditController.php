<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class EditController
{
    /**
     * Форма паспорта сериала
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Данные кабинета общие.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @urlParam series integer required ID сериала. Example: 1
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series): RedirectResponse
    {
        return to_route('projects.edit', $project);
    }
}
