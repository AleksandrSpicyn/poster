<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use App\Models\Episode;
use App\Models\Project;
use App\Models\Series;
use Spatie\LaravelData\Data;

class EpisodeScriptClaimData extends Data
{
    public function __construct(
        public Project $project,
        public Series $series,
        public Episode $episode,
    ) {}
}
