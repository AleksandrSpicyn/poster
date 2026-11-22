<?php

declare(strict_types=1);

namespace App\Data\Series;

use App\Enums\SeriesOutputMode;
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
        public SeriesOutputMode $output_mode = SeriesOutputMode::EPISODES,
        public ?bool $auto_publish = null,
    ) {}
}
