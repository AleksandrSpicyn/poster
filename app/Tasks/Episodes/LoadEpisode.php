<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Models\Episode;
use App\Repositories\EpisodeRepository;

readonly class LoadEpisode
{
    public function __construct(private EpisodeRepository $episodes) {}

    public function run(int $id): Episode
    {
        return $this->episodes->findOrFail($id);
    }
}
