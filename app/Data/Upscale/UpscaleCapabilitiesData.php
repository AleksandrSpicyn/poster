<?php

declare(strict_types=1);

namespace App\Data\Upscale;

use InvalidArgumentException;

final readonly class UpscaleCapabilitiesData
{
    public function __construct(
        public string $model,
        public int $maxDurationMs,
        public int $sourceWidth,
        public int $sourceHeight,
        public int $fps,
    ) {}

    public function assertSupports(UpscaleRequestData $request): void
    {
        if ($request->durationMs < 1 || $request->durationMs > $this->maxDurationMs) {
            throw new InvalidArgumentException('Unsupported upscale duration.');
        }

        if (filter_var($request->sourceUrl, FILTER_VALIDATE_URL) === false || parse_url($request->sourceUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('Upscale source requires HTTPS.');
        }
    }
}
