<?php

declare(strict_types=1);

namespace App\Data\Publications;

use App\Models\Channel;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use Spatie\LaravelData\Data;

class PublicationResolutionData extends Data
{
    public function __construct(
        public PublicationRequest $request,
        public Channel $channel,
        public Project $project,
        public ?Prompt $short = null,
    ) {}
}
