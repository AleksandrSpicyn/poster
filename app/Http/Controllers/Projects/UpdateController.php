<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\SaveProjectAction;
use App\Data\ProjectData;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UpdateController
{
    /**
     * Изменение проекта
     *
     * Требуются сессия, подтверждённый email и CSRF. История и каналы сохраняются.
     * Для Inertia PATCH redirect имеет HTTP 303, для обычного form submit — 302.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @urlParam project integer required ID проекта. Example: 1
     * @bodyParam name string required Название до 255 символов. Example: Wildlife
     * @bodyParam prompt string required Базовый промпт до 10000 символов. Example: Original short stories about animals
     * @bodyParam content_mode string Тип проекта. Проект с сериалом нельзя преобразовать в short. Enum: short,series Example: short
     * @bodyParam is_active boolean Включён ли проект. false останавливает выпуск и блокирует новые этапы; отсутствие поля сохраняет текущий флаг. Example: false
     * @bodyParam series_specification object Паспорт, обязателен для series; premise, visual_style, language, audience, audio_mode, episode_duration_seconds, narrative_rules, story_outline.
     * @bodyParam series_lock_version integer Версия настроек существующего сериала, обязательна при их изменении. Example: 0
     * @bodyParam automation_enabled boolean Автоматически подготовить и опубликовать выпуск. Example: false
     * @bodyParam output_mode string Способ выпуска: episodes или single_video. Example: episodes
     * @bodyParam series_specification.shot_duration_seconds integer Целевая длительность генерации 4–30 секунд, по умолчанию 15. Example: 15
     * @bodyParam episode_limit integer Число серий 1–1000, null для продолжающегося сериала. Example: 5
     * @bodyParam channel_ids integer[] Каналы проекта; [] отключает все, отсутствие сохраняет связи. Только оператор manageChannels. Example: [1,2]
     * @response 302
     * @response 303
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422 {"message":"Enter a project name.","errors":{"name":["Enter a project name."]}}
     */
    public function __invoke(Project $project, ProjectData $data, #[CurrentUser] User $user, SaveProjectAction $action): RedirectResponse
    {
        $action->run($data, $project, $user);
        Inertia::flash('success', 'Project updated.');

        return to_route('projects.show', $project);
    }
}
