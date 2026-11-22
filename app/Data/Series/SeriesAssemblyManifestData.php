<?php

declare(strict_types=1);

namespace App\Data\Series;

use RuntimeException;
use Spatie\LaravelData\Data;

class SeriesAssemblyManifestData extends Data
{
    /** @param list<array{number: int, episode_id: int, assembly_id: int, media_id: int, checksum: string, duration_ms: int}> $parts */
    public function __construct(public array $parts, public int $specification_version, public string $plan_hash) {}

    public static function fromSnapshot(mixed $snapshot): self
    {
        if (! is_array($snapshot) || ! is_array($snapshot['parts'] ?? null) || ! array_is_list($snapshot['parts'])
            || ! is_int($snapshot['specification_version'] ?? null) || ! is_string($snapshot['plan_hash'] ?? null)
            || count($snapshot['parts']) < 1 || count($snapshot['parts']) > 20) {
            throw new RuntimeException('The final assembly manifest is invalid.');
        }

        $parts = [];

        foreach ($snapshot['parts'] as $part) {
            if (! is_array($part) || ! is_int($part['number'] ?? null) || ! is_int($part['episode_id'] ?? null)
                || ! is_int($part['assembly_id'] ?? null) || ! is_int($part['media_id'] ?? null)
                || ! is_string($part['checksum'] ?? null) || ! is_int($part['duration_ms'] ?? null) || $part['duration_ms'] < 1) {
                throw new RuntimeException('The final assembly has an invalid part.');
            }

            $parts[] = ['number' => $part['number'], 'episode_id' => $part['episode_id'], 'assembly_id' => $part['assembly_id'],
                'media_id' => $part['media_id'], 'checksum' => $part['checksum'], 'duration_ms' => $part['duration_ms']];
        }

        return new self($parts, $snapshot['specification_version'], $snapshot['plan_hash']);
    }
}
