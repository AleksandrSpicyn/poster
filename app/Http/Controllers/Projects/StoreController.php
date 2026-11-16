<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\SaveProjectAction;
use App\Data\ProjectData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StoreController
{
    /**
     * Создание проекта
     *
     * Требуются сессия, подтверждённый email и CSRF. Тип series сохраняет также
     * настройки сериала; включённая автоматизация ставит один выпуск в очередь.
     * Настройки сериалов доступны только оператору manageSeries.
     * Browser-валидация возвращает redirect с errors, JSON-вариант — 422.
     *
     * @group Кабинет
     * @subgroup Проекты
     * @authenticated
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @bodyParam name string required Название до 255 символов. Example: Wildlife
     * @bodyParam prompt string required Базовый промпт до 10000 символов. Example: Original short stories about animals
     * @bodyParam content_mode string Тип проекта, short по умолчанию. Enum: short,series Example: short
     * @bodyParam is_active boolean Включён ли проект, true по умолчанию. Выключенный проект не генерирует и не публикует контент. Example: true
     * @bodyParam series_specification object Паспорт, обязателен для series; premise, visual_style, language, audience, audio_mode, episode_duration_seconds, narrative_rules, story_outline.
     * @bodyParam automation_enabled boolean Автоматически подготовить и опубликовать выпуск. Example: false
     * @bodyParam episode_limit integer Число серий 1–1000, null для продолжающегося сериала. Example: 5
     * @bodyParam channel_ids integer[] Каналы проекта; [] отключает все, отсутствие сохраняет связи. Только оператор manageChannels. Example: [1,2]
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 422 {"message":"Enter a project name.","errors":{"name":["Enter a project name."]}}
     */
    public function __invoke(ProjectData $data, #[CurrentUser] User $user, SaveProjectAction $action): RedirectResponse
    {
        $project = $action->run($data, actor: $user);
        Inertia::flash('success', 'Project created.');

        return to_route('projects.show', $project);
    }
}
