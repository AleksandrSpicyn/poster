<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\SaveEpisodeScriptData;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Repositories\MediaRepository;
use App\Tasks\Episodes\BuildEpisodeContext;
use App\Tasks\Episodes\ReadSeasonScreenplay;
use App\Tasks\Episodes\SaveEpisodeScript;

readonly class SaveEpisodeScriptAction
{
    public function __construct(private BuildEpisodeContext $context, private SaveEpisodeScript $save, private ReadSeasonScreenplay $season, private MediaRepository $media) {}

    public function importSeason(Episode $episode, int $assetId, int $version): EpisodeRevision
    {
        $asset = $this->media->seasonAsset($episode->series_id, $assetId);
        $context = $this->context->run($episode);
        $context['season_asset_id'] = $asset->id;
        $context['season_sha256'] = $asset->getCustomProperty('checksum');

        return $this->save->run($episode, $this->season->run($episode, $asset), $version, $context);
    }

    public function run(Episode $episode, SaveEpisodeScriptData $data): EpisodeRevision
    {
        return $this->save->run($episode, $data->script, $data->lock_version, $this->context->run($episode));
    }
}
