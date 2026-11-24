<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicationRequests;

use App\Actions\Episodes\ResolveEpisodePublicationAction;
use App\Data\Episodes\ResolveEpisodePublicationData;
use App\Http\Controllers\EpisodeController;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class ResolveController extends EpisodeController
{
    /**
     * Подтверждение неизвестного результата публикации
     *
     * Проверяет внешний ID через чтение API провайдера и принадлежность видео
     * подключённому аккаунту. Сохраняет подтверждённый Post без повторной отправки.
     * Требуются сессия, подтверждённый email, CSRF и правильная цепочка родителей.
     * Недостаток read permissions или чужое видео оставляют исход неизвестным.
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam episode integer required ID эпизода сериала. Example: 1
     * @urlParam revision integer required ID версии сценария. Example: 1
     * @urlParam assembly integer required ID сборки версии. Example: 1
     * @urlParam publication integer required ID запроса публикации сборки. Example: 1
     * @bodyParam external_id string required ID внешнего видео до 128 символов, без URL. Example: 17918920912340654
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(
        Series $series,
        Episode $episode,
        EpisodeRevision $revision,
        EpisodeAssembly $assembly,
        PublicationRequest $publication,
        ResolveEpisodePublicationData $data,
        #[CurrentUser] User $user,
        ResolveEpisodePublicationAction $action,
    ): RedirectResponse {
        $action->run($publication, $user, $data->external_id);

        return $this->redirectToEpisode($series, $episode);
    }
}
