<?php

declare(strict_types=1);

namespace App\Http\Controllers\EpisodeAssemblies;

use App\Actions\Episodes\RequestEpisodeAssemblyAction;
use App\Data\Episodes\AssemblyManifestData;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class StoreController
{
    /**
     * Сборка эпизода из выбранных дублей
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
     * @bodyParam shots object[] required Дубли в порядке планов сценария с prompt_id и trim_start_ms/trim_end_ms.
     * @bodyParam shots[].prompt_id integer required ID готового дубля соответствующего плана. Example: 1
     * @bodyParam shots[].trim_start_ms integer Начало обрезки от 0 мс. Example: 0
     * @bodyParam shots[].trim_end_ms integer Конец обрезки или null для конца плана. Остаток не менее 1 мс. Example: 5000
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, EpisodeRevision $revision, AssemblyManifestData $data, RequestEpisodeAssemblyAction $action): RedirectResponse
    {
        $action->run($revision, $data);

        return to_route('episodes.show', ['series' => $series, 'episode' => $episode]);
    }
}
