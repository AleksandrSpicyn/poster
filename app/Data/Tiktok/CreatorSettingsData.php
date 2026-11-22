<?php

declare(strict_types=1);

namespace App\Data\Tiktok;

use Spatie\LaravelData\Data;

final class CreatorSettingsData extends Data
{
    /** @param list<string> $privacy */
    public function __construct(
        public readonly string $nickname,
        public readonly array $privacy,
        public readonly int $duration,
        public readonly bool $comment,
        public readonly bool $duet,
        public readonly bool $stitch,
    ) {}
}
