<?php

declare(strict_types=1);

namespace App\Data\Upscale;

use App\Enums\UpscaleExport;

final readonly class UpscaleRequestData
{
    public function __construct(
        public string $sourceUrl,
        public int $durationMs,
        public UpscaleExport $export,
    ) {}
}
