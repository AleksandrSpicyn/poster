<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Actions\Series\SaveSeriesAction;
use App\Data\Series\SeriesData;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StoreController
{
    /**
     * Создание сериала проекта
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные ресурсы ограничены своим родителем. Данные кабинета общие.
     *
     * @group Кабинет
     * @subgroup Сериалы и персонажи
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @bodyParam name string required Название до 255 символов. Example: Moonlight café
     * @bodyParam specification object required Паспорт сериала.
     * @bodyParam specification.premise string required Мир и исходная история до 20000 символов. Example: Two friends run a café.
     * @bodyParam specification.visual_style string Визуальный стиль до 2000 символов. Example: animated watercolor
     * @bodyParam specification.language string Язык до 35 символов. Example: en
     * @bodyParam specification.audience string Аудитория. Enum: general,adult,children Example: general
     * @bodyParam specification.audio_mode string Режим звука: native по умолчанию, silent без реплик и звука. Enum: native,silent Example: native
     * @bodyParam specification.episode_duration_seconds integer Длительность эпизода 4–300 секунд. Example: 30
     * @bodyParam specification.narrative_rules string Правила повествования до 20000 символов. Example: End on a question.
     * @response 302
     * @response 422
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     */
    public function __invoke(Project $project, SeriesData $data, SaveSeriesAction $action): RedirectResponse
    {
        $series = $action->run($project, $data);
        Inertia::flash('success', 'Series created.');

        return to_route('series.show', ['project' => $project, 'series' => $series]);
    }
}
