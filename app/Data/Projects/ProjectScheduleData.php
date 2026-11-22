<?php

declare(strict_types=1);

namespace App\Data\Projects;

use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Timezone;
use Spatie\LaravelData\Data;

final class ProjectScheduleData extends Data
{
    public function __construct(
        public bool $enabled,
        #[DateFormat('H:i')] public string $time,
        #[Timezone] public string $timezone,
    ) {}
}
