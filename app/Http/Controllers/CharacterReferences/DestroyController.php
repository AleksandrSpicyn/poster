<?php

declare(strict_types=1);

namespace App\Http\Controllers\CharacterReferences;

use App\Actions\Characters\RemoveCharacterReferenceAction;
use App\Data\ExpectedVersionData;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DestroyController
{
    /**
     * Удаление референса из черновика
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
     * @urlParam media integer required ID референса. Example: 1
     * @bodyParam lock_version integer required Версия черновика. Example: 0
     * @response 302
     * @response 422
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series, Character $character, CharacterRevision $revision, Media $media, ExpectedVersionData $data, RemoveCharacterReferenceAction $action): RedirectResponse
    {
        $action->run($revision, $media, $data->lock_version);

        return to_route('characters.show', [
            'project' => $project,
            'series' => $series,
            'character' => $character,
        ]);
    }
}
