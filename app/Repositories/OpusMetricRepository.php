<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ChannelType;
use App\Models\OpusClip;
use App\Models\OpusClipMetric;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

final readonly class OpusMetricRepository
{
    /** @return Collection<int, OpusClipMetric> */
    public function observations(Project $project, string $day): Collection
    {
        return OpusClipMetric::query()->whereRelation('clip.source', 'project_id', $project->id)
            ->where('observed_on', $day)->with('clip.source')->get();
    }

    /** @param array<string, int|float> $metrics */
    public function observe(OpusClip $clip, ChannelType $platform, string $mediaId, array $metrics): void
    {
        OpusClipMetric::query()->updateOrCreate([
            'opus_clip_id' => $clip->id, 'platform' => $platform->value, 'media_id' => $mediaId,
            'observed_on' => now('UTC')->toDateString(),
        ], ['metrics' => $metrics]);
    }
}
