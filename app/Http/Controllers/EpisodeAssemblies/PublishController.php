<?php

declare(strict_types=1);

namespace App\Http\Controllers\EpisodeAssemblies;

use App\Actions\Episodes\RequestEpisodePublicationAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class PublishController
{
    /**
     * Публикация принятого эпизода в выбранные каналы
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
     * @bodyParam channel_ids integer[] required ID поддержанных каналов проекта, 1–20 элементов. Example: [1]
     * @bodyParam caption string Разовая подпись до 1000 символов. Пустая строка отправляет эпизод без подписи. Example: Енот находит карту.
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, EpisodeRevision $revision, EpisodeAssembly $assembly, PublishEpisodeData $data, #[CurrentUser] User $user, RequestEpisodePublicationAction $action): RedirectResponse
    {
        $action->run($assembly, $data, $user);

        return to_route('episodes.show', ['series' => $series, 'episode' => $episode]);
    }
}
