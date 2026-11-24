<?php

declare(strict_types=1);

namespace App\Http\Controllers\CharacterRevisions;

use App\Actions\Characters\CreateCharacterRevisionAction;
use App\Data\Characters\CreateCharacterRevisionData;
use App\Http\Controllers\CharacterController;
use App\Models\Character;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class StoreController extends CharacterController
{
    /**
     * Создание черновой версии персонажа
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
     * @bodyParam description string required Внешность и характер до 10000 символов. Example: A curious young artist with short dark hair.
     * @bodyParam title string Название версии до 255 символов.
     * @bodyParam file file Первое изображение версии (PNG, JPEG или WebP до 10 МБ).
     * @bodyParam appearance object Визуальные характеристики.
     * @bodyParam voice object Характеристики голоса.
     * @bodyParam lock_version integer Версия изменяемого черновика. Example: 0
     * @response 302
     * @response 422
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, Character $character, CreateCharacterRevisionData $data, CreateCharacterRevisionAction $action): RedirectResponse
    {
        $action->run($character, $data);

        return $this->redirectToCharacter($project, $series, $character);
    }
}
