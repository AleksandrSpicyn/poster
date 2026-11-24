<?php

declare(strict_types=1);

namespace App\Http\Controllers\EpisodeScripts;

use App\Actions\Episodes\ApproveEpisodeScriptAction;
use App\Http\Controllers\EpisodeController;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class ApproveController extends EpisodeController
{
    /**
     * Утверждение просмотренного сценария
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные версии, сцены, планы и сборки ограничены родителями.
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam episode integer required ID эпизода. Example: 1
     * @urlParam revision integer required ID версии сценария. Example: 1
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, EpisodeRevision $revision, #[CurrentUser] User $user, ApproveEpisodeScriptAction $action): RedirectResponse
    {
        $action->run($revision, $user);

        return $this->redirectToEpisode($series, $episode);
    }
}
