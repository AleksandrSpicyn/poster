<?php

declare(strict_types=1);

namespace App\Data\Publications;

use App\Models\Channel;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\SeriesAssembly;
use Spatie\LaravelData\Data;

class PublicationResolutionData extends Data
{
    public function __construct(
        public PublicationRequest $request,
        public Channel $channel,
        public EpisodeAssembly|SeriesAssembly $assembly,
        public Series $series,
    ) {}
}
