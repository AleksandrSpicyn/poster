<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\EpisodeWorkspaceData;
use App\Models\Episode;
use App\Tasks\Episodes\GetEpisode;

readonly class GetEpisodeAction
{
    public function __construct(private GetEpisode $getEpisode) {}

    public function run(Episode $episode): EpisodeWorkspaceData
    {
        return $this->getEpisode->run($episode);
    }
}
