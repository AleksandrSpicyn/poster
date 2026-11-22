<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use RuntimeException;
use Spatie\LaravelData\Data;

class AssemblyClipData extends Data
{
    public function __construct(
        public readonly int $mediaId,
        public readonly int $promptId,
        public readonly string $checksum,
        public readonly int $trimStartMs,
        public readonly int $trimEndMs,
        public readonly int $durationMs,
        public readonly bool $muteAudio,
    ) {}

    public static function fromSnapshot(mixed $shot): self
    {
        if (! is_array($shot) || ! is_int($shot['media_id'] ?? null) || ! is_int($shot['prompt_id'] ?? null)
            || ! is_string($shot['checksum'] ?? null) || ! is_int($shot['trim_start_ms'] ?? null)
            || ! is_int($shot['trim_end_ms'] ?? null) || ! is_int($shot['duration_ms'] ?? null)
            || (array_key_exists('mute_audio', $shot) && ! is_bool($shot['mute_audio']))
            || $shot['trim_start_ms'] < 0 || $shot['duration_ms'] < 1
            || $shot['trim_end_ms'] - $shot['trim_start_ms'] !== $shot['duration_ms']) {
            throw new RuntimeException('The assembly manifest contains an invalid trim or media reference.');
        }

        return new self($shot['media_id'], $shot['prompt_id'], $shot['checksum'], $shot['trim_start_ms'], $shot['trim_end_ms'], $shot['duration_ms'], $shot['mute_audio'] ?? false);
    }
}
