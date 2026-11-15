<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Enums\ContentAudience;
use App\Enums\SeriesAudioMode;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Inertia\Response;

class CreateController
{
    /**
     * Форма нового сериала
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Данные кабинета общие.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project): Response
    {
        return inertia('Series/Form', [
            'project' => ProjectResource::make($project)->resolve(),
            'series' => null,
            'audiences' => ContentAudience::labels(),
            'audioModes' => SeriesAudioMode::labels(),
            'defaultAudioMode' => SeriesAudioMode::NATIVE->value,
        ]);
    }
}
