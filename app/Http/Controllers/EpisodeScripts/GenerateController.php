<?php

declare(strict_types=1);

namespace App\Http\Controllers\EpisodeScripts;

use App\Actions\Episodes\RequestEpisodeScriptAction;
use App\Data\Episodes\GenerateEpisodeScriptData;
use App\Http\Controllers\EpisodeController;
use App\Models\Episode;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class GenerateController extends EpisodeController
{
    /**
     * Запрос сценария по заданию в очередь
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные версии, сцены, планы и сборки ограничены родителями.
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam episode integer required ID эпизода. Example: 1
     * @bodyParam brief string required Задание до 5000 символов. Example: Continue the last unfinished scene.
     * @bodyParam lock_version integer required Версия эпизода. Example: 0
     * @bodyParam request_key string required UUID намерения запуска. Example: 86ac98fd-8d5e-4c99-a072-b5ac1a42f874
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, GenerateEpisodeScriptData $data, #[CurrentUser] User $user, RequestEpisodeScriptAction $action): RedirectResponse
    {
        $action->run($episode, $data, $user);

        return $this->redirectToEpisode($series, $episode);
    }
}
