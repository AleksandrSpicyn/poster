<?php

declare(strict_types=1);

namespace App\Data\Upscale;

final readonly class UpscaleReceiptData
{
    public function __construct(
        public string $id,
        public string $statusUrl,
        public string $resultUrl,
    ) {}
}
