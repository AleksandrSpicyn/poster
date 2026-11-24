<?php

declare(strict_types=1);

namespace App\Data\Upscale;

use App\Enums\UpscaleQueueStatus;

final readonly class UpscaleStatusData
{
    public function __construct(
        public UpscaleQueueStatus $status,
        public ?string $videoUrl = null,
    ) {}
}
