<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shots;

use App\Actions\Shots\RetryShotDownloadAction;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\Prompt;
use App\Models\Scene;
use App\Models\Series;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class RetryDownloadController
{
    /**
     * Повтор загрузки готового результата
     *
     * Возобновляет только существующую локальную стадию загрузки этого дубля.
     * Повторная генерация видео не выполняется. Требуются сессия,
     * подтверждённый email и CSRF; модели ограничены текущими родителями.
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam episode integer required ID эпизода. Example: 1
     * @urlParam revision integer required ID версии сценария. Example: 1
     * @urlParam scene integer required ID сцены. Example: 1
     * @urlParam shot integer required ID плана. Example: 1
     * @urlParam prompt integer required ID дубля. Example: 1
     * @response 302 scenario="Загрузка поставлена в очередь или переход на вход"
     * @response 401 scenario="Нет JSON-аутентификации" {"message":"Unauthenticated."}
     * @response 403 scenario="Email не подтверждён" {"message":"Your email address is not verified."}
     * @response 403 scenario="Нет операторского доступа" {"message":"This action is unauthorized."}
     * @response 404 scenario="Дубль вне области плана или результат отсутствует"
     * @response 422 scenario="Контекст устарел или загрузка уже выполняется"
     */
    public function __invoke(Series $series, Episode $episode, EpisodeRevision $revision, Scene $scene, Shot $shot, Prompt $prompt, #[CurrentUser] User $user, RetryShotDownloadAction $action): RedirectResponse
    {
        $action->run($prompt, $user);

        return to_route('episodes.show', ['series' => $series, 'episode' => $episode]);
    }
}
