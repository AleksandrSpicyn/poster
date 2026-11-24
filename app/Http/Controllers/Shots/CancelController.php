<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shots;

use App\Actions\Shots\CancelShotGenerationAction;
use App\Http\Controllers\EpisodeController;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\Prompt;
use App\Models\Scene;
use App\Models\Series;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class CancelController extends EpisodeController
{
    /**
     * Остановка последующих этапов дубля
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные версии, сцены, планы и сборки ограничены родителями.
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam episode integer required ID эпизода. Example: 1
     * @urlParam revision integer required ID сценария. Example: 1
     * @urlParam scene integer required ID сцены. Example: 1
     * @urlParam shot integer required ID плана. Example: 1
     * @urlParam prompt integer required ID дубля. Example: 1
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, EpisodeRevision $revision, Scene $scene, Shot $shot, Prompt $prompt, #[CurrentUser] User $user, CancelShotGenerationAction $action): RedirectResponse
    {
        $action->run($prompt, $user);

        return $this->redirectToEpisode($series, $episode)->withFragment('shots');
    }
}
