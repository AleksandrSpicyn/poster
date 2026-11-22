<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use RuntimeException;
use Spatie\LaravelData\Data;

class EpisodeAssemblyPlanData extends Data
{
    /** @param list<AssemblyClipData> $shots */
    public function __construct(public readonly array $shots, public readonly int $durationMs) {}

    /** @param array<string, mixed> $manifest */
    public static function fromManifest(array $manifest): self
    {
        $shots = $manifest['shots'] ?? null;

        if (! is_array($shots) || ! array_is_list($shots) || $shots === [] || count($shots) > 100) {
            throw new RuntimeException('The assembly manifest is invalid.');
        }

        $clips = collect($shots)->map(AssemblyClipData::fromSnapshot(...));
        $duration = $clips->sum(fn(AssemblyClipData $clip): int => $clip->durationMs);

        if ($duration < 1 || $duration > 600000) {
            throw new RuntimeException('The assembly duration is outside the supported range.');
        }

        return new self(array_values($clips->all()), $duration);
    }
}
