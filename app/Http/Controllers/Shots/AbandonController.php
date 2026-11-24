<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shots;

use App\Actions\Shots\AbandonShotGenerationAction;
use App\Data\Shots\AbandonShotGenerationData;
use App\Http\Controllers\EpisodeController;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Scene;
use App\Models\Series;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class AbandonController extends EpisodeController
{
    /**
     * Закрыть неизвестный запуск по решению оператора
     *
     * Сохраняет решение отказаться от локального продолжения операции с неизвестным
     * внешним исходом. Не отменяет удалённую задачу и не подтверждает отсутствие списания.
     * Новый дубль запускается отдельным запросом. Требуются сессия, подтверждённый email,
     * операторский доступ и CSRF. Вложенные модели ограничены текущими родителями.
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
     * @urlParam operation integer required ID неизвестной операции этого дубля. Example: 1
     * @bodyParam note string required Причина решения, до 1000 символов. Example: Проверено вручную, исход установить невозможно
     * @bodyParam accept_unknown_outcome boolean required Явное принятие неизвестного внешнего исхода; требуется true. Example: true
     * @response 302 scenario="Решение сохранено или переход на вход"
     * @response 401 scenario="Нет JSON-аутентификации" {"message":"Unauthenticated."}
     * @response 403 scenario="Email не подтверждён" {"message":"Your email address is not verified."}
     * @response 403 scenario="Нет операторского доступа" {"message":"This action is unauthorized."}
     * @response 404 scenario="Операция вне области дубля"
     * @response 422 scenario="Нет подтверждения, причины или неизвестной операции"
     */
    public function __invoke(Series $series, Episode $episode, EpisodeRevision $revision, Scene $scene, Shot $shot, Prompt $prompt, GenerationOperation $operation, AbandonShotGenerationData $data, #[CurrentUser] User $user, AbandonShotGenerationAction $action): RedirectResponse
    {
        $action->run($operation, $data, $user);

        return $this->redirectToEpisode($series, $episode)->withFragment('shots');
    }
}
