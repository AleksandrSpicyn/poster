<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\CreateEpisodeData;
use App\Models\Episode;
use App\Models\Series;
use App\Tasks\Episodes\CreateEpisode;

readonly class CreateEpisodeAction
{
    public function __construct(private CreateEpisode $createEpisode) {}

    public function run(Series $series, CreateEpisodeData $data): Episode
    {
        return $this->createEpisode->run($series, $data);
    }
}
