<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\Project;
use App\Models\Series;
use Spatie\LaravelData\Data;

class EpisodePublishingContextData extends Data
{
    public function __construct(
        public EpisodeRevision $revision,
        public Episode $episode,
        public Series $series,
        public Project $project,
    ) {}
}
