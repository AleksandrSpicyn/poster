<?php

declare(strict_types=1);

namespace App\Data\Projects;

use App\Models\Project;
use Spatie\LaravelData\Data;

class ProjectFormData extends Data
{
    /** @param list<PublishingSourceData> $channelSources */
    public function __construct(
        public ?Project $project,
        public array $channelSources,
    ) {}
}
