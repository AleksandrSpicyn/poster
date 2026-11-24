<?php

declare(strict_types=1);

namespace App\Http\Controllers\CharacterRevisions;

use App\Actions\Characters\ApproveCharacterRevisionAction;
use App\Data\ExpectedVersionData;
use App\Http\Controllers\CharacterController;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class ApproveController extends CharacterController
{
    /**
     * Утверждение версии внешности и набора референсов
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
     * @urlParam revision integer required ID версии. Example: 1
     * @bodyParam lock_version integer required Версия записи. Example: 0
     * @response 302
     * @response 422
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, Character $character, CharacterRevision $revision, ExpectedVersionData $data, #[CurrentUser] User $user, ApproveCharacterRevisionAction $action): RedirectResponse
    {
        $action->run($revision, $user, $data->lock_version);

        return $this->redirectToCharacter($project, $series, $character);
    }
}
