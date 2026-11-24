<?php

declare(strict_types=1);

namespace App\Http\Controllers\Episodes;

use App\Actions\Episodes\CreateEpisodeAction;
use App\Data\Episodes\CreateEpisodeData;
use App\Http\Controllers\EpisodeController;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class StoreController extends EpisodeController
{
    /**
     * Создание очередного эпизода
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные версии, сцены, планы и сборки ограничены родителями.
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @bodyParam title string required Название до 255 символов. Example: The hidden key
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, CreateEpisodeData $data, CreateEpisodeAction $action): RedirectResponse
    {
        $episode = $action->run($series, $data);

        return $this->redirectToEpisode($series, $episode);
    }
}
