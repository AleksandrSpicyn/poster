<?php

declare(strict_types=1);

namespace App\Http\Controllers\Characters;

use App\Actions\Characters\ArchiveCharacterAction;
use App\Data\ExpectedVersionData;
use App\Models\Character;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class ArchiveController
{
    /**
     * Архивирование персонажа с сохранением версий
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Данные кабинета общие.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam character integer required ID персонажа. Example: 1
     * @bodyParam lock_version integer required Текущая версия. Example: 0
     * @response 302
     * @response 422
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, Character $character, ExpectedVersionData $data, ArchiveCharacterAction $action): RedirectResponse
    {
        $action->run($character, $data->lock_version);

        return to_route('characters.show', ['project' => $project, 'series' => $series, 'character' => $character]);
    }
}
