<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProductionRuns;

use App\Actions\Production\StartProductionAction;
use App\Data\Production\StartProductionData;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StoreController
{
    /**
     * Запустить автоматический выпуск
     *
     * Создаёт одно намерение выпуска. Сериал проходит сценарий, генерацию, монтаж,
     * сохранение истории и публикацию; обычный проект создаёт отдельный ролик.
     * Повтор активного запроса не создаёт второй платный запуск.
     *
     * @group Кабинет
     * @subgroup Автоматическое производство
     * @authenticated
     * @urlParam project integer required ID проекта. Example: 1
     * @bodyParam request_key string required Ключ запроса до 100 символов. Example: production-2026-11-16-1
     * @bodyParam series_id integer ID сериала этого проекта; без него обрабатываются включённые сериалы. Example: 1
     * @bodyParam channel_ids integer[] Каналы выбранного сериала. Пустой список сохраняет видео без отправки. Example: [1,2]
     * @response 302
     * @response 401
     * @response 403
     * @response 404
     * @response 422
     */
    public function __invoke(Project $project, StartProductionData $data, #[CurrentUser] User $user, StartProductionAction $action): RedirectResponse
    {
        $runs = $action->run($project, $data->request_key, $user, $data->series_id, $data->channel_ids);
        Inertia::flash('success', $runs->isEmpty() ? 'No new episode is due. Check automation settings and the episode limit.' : 'Production queued.');

        return back();
    }
}
