<?php

declare(strict_types=1);

namespace App\Http\Controllers\Characters;

use App\Actions\Characters\SaveCharacterAction;
use App\Data\Characters\CharacterData;
use App\Http\Controllers\CharacterController;
use App\Models\Character;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class UpdateController extends CharacterController
{
    /**
     * Изменение имени персонажа
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
     * @bodyParam name string required Имя до 255 символов. Example: Mira
     * @bodyParam lock_version integer required Текущая версия. Example: 0
     * @response 302
     * @response 422
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, Character $character, CharacterData $data, SaveCharacterAction $action): RedirectResponse
    {
        $action->run($series, $data, $character);

        return $this->redirectToCharacter($project, $series, $character);
    }
}
