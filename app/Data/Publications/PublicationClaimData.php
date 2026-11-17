<?php

declare(strict_types=1);

namespace App\Data\Publications;

use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Models\User;
use Spatie\LaravelData\Data;

class PublicationClaimData extends Data
{
    public function __construct(
        public EpisodeAssembly|SeriesAssembly $assembly,
        public ?Episode $episode,
        public Series $series,
        public Project $project,
        public ?ProductionRun $productionRun,
        public PublicationRequest $request,
        public ?User $user,
    ) {}
}
