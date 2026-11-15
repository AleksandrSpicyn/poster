<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\Series\ArchiveSeriesAction;
use App\Data\ExpectedVersionData;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ArchiveController
{
    /**
     * Архивирование сериала без удаления истории
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Данные кабинета общие.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @urlParam series integer required ID сериала. Example: 1
     * @bodyParam lock_version integer required Текущая версия записи. Example: 0
     * @response 302
     * @response 422
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, ExpectedVersionData $data, ArchiveSeriesAction $action): RedirectResponse
    {
        $action->run($series, $data->lock_version);
        Inertia::flash('success', 'Series archived.');

        return to_route('series.show', ['project' => $project, 'series' => $series]);
    }
}
