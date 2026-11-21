<?php

declare(strict_types=1);

namespace App\Data\OpusClip;

final readonly class ImportedClipData
{
    /** @param list<array{platform: string, status: string|null, url: string|null, publish_at: string|null}> $publications */
    public function __construct(
        public string $id,
        public ?string $title,
        public ?int $durationMs,
        public ?float $score,
        public array $publications,
    ) {}
}
