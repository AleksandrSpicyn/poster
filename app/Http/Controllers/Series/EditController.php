<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Enums\ContentAudience;
use App\Enums\SeriesAudioMode;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SeriesResource;
use App\Models\Project;
use App\Models\Series;
use Inertia\Response;

class EditController
{
    /**
     * Форма паспорта сериала
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Данные кабинета общие.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @urlParam series integer required ID сериала. Example: 1
     * @response 200
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, Series $series): Response
    {
        return inertia('Series/Form', [
            'project' => ProjectResource::make($project)->resolve(),
            'series' => SeriesResource::make($series)->resolve(),
            'audiences' => ContentAudience::labels(),
            'audioModes' => SeriesAudioMode::labels(),
            'defaultAudioMode' => SeriesAudioMode::NATIVE->value,
        ]);
    }
}
