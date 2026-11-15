<?php

declare(strict_types=1);

namespace App\Http\Controllers\EpisodeMedia;

use App\Actions\Episodes\GetEpisodeMediaAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShowController
{
    /**
     * Приватный просмотр дубля или сборки эпизода
     *
     * Требуются сессия и подтверждённый email. Файл принадлежит сериальному
     * Prompt либо EpisodeAssembly и хранится на приватном диске.
     *
     * @group Кабинет
     * @subgroup Сериалы
     * @authenticated
     * @urlParam media integer required ID приватного сериального медиа. Example: 1
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Media $media, GetEpisodeMediaAction $action): BinaryFileResponse
    {
        return response()->file($action->run($media), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }
}
