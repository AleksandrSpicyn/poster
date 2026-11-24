<?php

declare(strict_types=1);

namespace App\Http\Controllers\CharacterReferences;

use App\Actions\Characters\GetCharacterReferenceAction;
use App\Http\Controllers\Controller;
use App\Models\CharacterRevision;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShowController extends Controller
{
    /**
     * Просмотр приватного изображения-референса
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Данные кабинета общие.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @urlParam revision integer required ID версии персонажа. Example: 1
     * @urlParam media integer required ID референса этой версии. Example: 1
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(CharacterRevision $revision, Media $media, GetCharacterReferenceAction $action): BinaryFileResponse
    {
        return $this->privateFile($action->run($revision, $media));
    }
}
