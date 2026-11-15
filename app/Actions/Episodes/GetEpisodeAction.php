<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\EpisodeScriptData;
use App\Models\Channel;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use App\Tasks\Episodes\GetEpisode;
use Illuminate\Database\Eloquent\Collection;

readonly class GetEpisodeAction
{
    public function __construct(private GetEpisode $getEpisode) {}

    /** @return array{episode: Episode, characters: Collection<int, CharacterRevision>, assemblies: Collection<int, EpisodeAssembly>, publications: Collection<int, PublicationRequest>, channels: Collection<int, Channel>, script: EpisodeScriptData|null} */
    public function run(Episode $episode): array
    {
        return $this->getEpisode->run($episode);
    }
}
