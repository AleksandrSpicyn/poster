<?php

declare(strict_types=1);

namespace App\Http\Controllers\EpisodeScripts;

use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\ImportSeasonData;
use App\Http\Controllers\EpisodeController;
use App\Models\Episode;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class ImportSeasonController extends EpisodeController
{
    /**
     * Импорт эпизода из сохранённого сценария сезона
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @response 302
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, int $assetId, ImportSeasonData $data, SaveEpisodeScriptAction $action): RedirectResponse
    {
        $action->importSeason($episode, $assetId, $data->lock_version);

        return $this->redirectToEpisode($series, $episode);
    }
}
