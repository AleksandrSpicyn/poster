<?php

declare(strict_types=1);

namespace App\Http\Controllers\CharacterReferences;

use App\Actions\Characters\UploadCharacterReferenceAction;
use App\Data\Characters\CharacterReferenceData;
use App\Http\Controllers\CharacterController;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class StoreController extends CharacterController
{
    /**
     * Загрузка приватного референса версии персонажа
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
     * @urlParam revision integer required ID черновика. Example: 1
     * @bodyParam file file required PNG/JPEG/WebP или образец речи WAV/MP3 (2–30 с), до 10 МБ. No-example
     * @bodyParam view string required Ракурс или образец голоса: front, profile, three_quarter, full_body, expression, other, voice. Example: front
     * @bodyParam label string Подпись до 255 символов. Example: Mira, front view
     * @bodyParam lock_version integer required Версия черновика. Example: 0
     * @response 302
     * @response 422
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, Character $character, CharacterRevision $revision, CharacterReferenceData $data, UploadCharacterReferenceAction $action): RedirectResponse
    {
        $action->run($revision, $data);

        return $this->redirectToCharacter($project, $series, $character);
    }
}
