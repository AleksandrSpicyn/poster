<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shots;

use App\Actions\Shots\RequestShotGenerationAction;
use App\Data\Episodes\RequestShotData;
use App\Http\Controllers\EpisodeController;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\Scene;
use App\Models\Series;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class GenerateController extends EpisodeController
{
    /**
     * Генерация дубля плана
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные версии, сцены, планы и сборки ограничены родителями.
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam episode integer required ID эпизода. Example: 1
     * @urlParam revision integer required ID утверждённого сценария. Example: 1
     * @urlParam scene integer required ID сцены. Example: 1
     * @urlParam shot integer required ID плана. Example: 1
     * @bodyParam source_prompt_id integer Выбранный предыдущий готовый дубль. Example: 1
     * @bodyParam request_key string Ключ дедупликации до 100 символов. Example: shot-request-1
     * @bodyParam new_take boolean Создать новый дубль отдельным намерением. Example: false
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, EpisodeRevision $revision, Scene $scene, Shot $shot, RequestShotData $data, #[CurrentUser] User $user, RequestShotGenerationAction $action): RedirectResponse
    {
        $action->run($shot, $user, $data->source_prompt_id, $data->request_key, $data->new_take);

        return $this->redirectToEpisode($series, $episode)->withFragment('shots');
    }
}
