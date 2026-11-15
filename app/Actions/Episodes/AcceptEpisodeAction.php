<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\AcceptEpisodeData;
use App\Models\EpisodeAssembly;
use App\Models\User;
use App\Tasks\Episodes\AcceptEpisode;

readonly class AcceptEpisodeAction
{
    public function __construct(private AcceptEpisode $accept) {}

    public function run(EpisodeAssembly $assembly, AcceptEpisodeData $data, User $actor): EpisodeAssembly
    {
        return $this->accept->run($assembly, $data, $actor);
    }
}
