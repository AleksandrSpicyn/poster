<?php

declare(strict_types=1);

namespace App\Http\Controllers\EpisodeAssemblies;

use App\Actions\Episodes\AcceptEpisodeAction;
use App\Data\Episodes\AcceptEpisodeData;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class AcceptController
{
    /**
     * Принятие монтажа и подтверждение событий
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
     * @urlParam assembly integer required ID сборки этой версии. Example: 1
     * @bodyParam lock_version integer required Версия сборки. Example: 0
     * @bodyParam confirmed_events string required Подтверждённые события до 12000 символов. Example: The hero found the key.
     * @bodyParam character_state string required Состояние героев до 8000 символов. Example: Mira holds the key.
     * @bodyParam open_threads string Открытые сюжетные линии до 8000 символов. Example: Who owns the door?
     * @bodyParam scene_closed boolean required Завершена ли фактическая финальная сцена. Example: false
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, EpisodeRevision $revision, EpisodeAssembly $assembly, AcceptEpisodeData $data, #[CurrentUser] User $user, AcceptEpisodeAction $action): RedirectResponse
    {
        $action->run($assembly, $data, $user);

        return to_route('episodes.show', ['series' => $series, 'episode' => $episode]);
    }
}
