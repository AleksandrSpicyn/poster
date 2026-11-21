<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OpusClip;
use App\Models\OpusClipMetric;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OpusClip */
class OpusClipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'clip_id' => $this->clip_id, 'title' => $this->title,
            'source_url' => 'https://clip.opus.pro/clip/' . rawurlencode($this->source->external_id),
            'source' => $this->source->title ?? $this->source->external_id, 'game' => $this->source->game,
            'duration_ms' => $this->duration_ms, 'score' => $this->score, 'publications' => $this->publications,
            'observations' => $this->latestMetrics->map(fn(OpusClipMetric $row): array => [
                'platform' => $row->platform, 'media_id' => $row->media_id, 'date' => $row->observed_on, 'metrics' => $row->metrics,
            ])->all(),
        ];
    }
}
