<?php

declare(strict_types=1);

namespace App\Data\Series;

use App\Models\Project;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SeriesData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required]
        public SeriesSpecificationData $specification,
        #[Min(0)]
        public ?int $lock_version = null,
        public bool $automation_enabled = false,
        #[Min(1), Max(1000)]
        public ?int $episode_limit = null,
        #[Exists(Project::class, 'id')]
        public ?int $channel_source_project_id = null,
    ) {}
}
