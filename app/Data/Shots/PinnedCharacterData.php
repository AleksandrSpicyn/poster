<?php

declare(strict_types=1);

namespace App\Data\Shots;

use Spatie\LaravelData\Data;

class PinnedCharacterData extends Data
{
    /**
     * @param array<string, mixed> $appearance
     * @param array<string, mixed> $voice
     * @param list<PinnedReferenceData> $references
     */
    public function __construct(
        public readonly int $id,
        public readonly int $character_id,
        public readonly string $name,
        public readonly string $description,
        public readonly array $appearance,
        public readonly array $voice,
        public readonly array $references,
    ) {}
}
